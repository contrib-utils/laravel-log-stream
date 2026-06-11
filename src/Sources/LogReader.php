<?php

namespace LogScope\Sources;

use LogScope\Parsers\LogParser;
use LogScope\Parsers\ParseContext;
use LogScope\Support\Ids;
use LogScope\ValueObjects\LogEntry;
use LogScope\ValueObjects\LogFile;
use LogScope\ValueObjects\Page;

/**
 * Reads entries from a single log file using byte-offset seeking so tailing a
 * multi-GB file is O(window), never O(file).
 *
 * Backward (newest-first, the default) reads the file in reverse chunks to
 * locate the N newest entry-start offsets below a cursor, then parses just that
 * slice forward. Cursors are byte offsets: a "next" cursor is the start offset
 * of the oldest entry returned, so the following page reads strictly older
 * entries with no gap or overlap. Level/substring filters are applied after
 * parsing, and the reader keeps scanning windows until the page is full.
 */
class LogReader
{
    /** Bytes read per reverse chunk. */
    protected const CHUNK = 8192;

    /** Max bytes scanned for a single window before giving up (pathological input guard). */
    protected const MAX_WINDOW_BYTES = 2_097_152; // 2 MiB

    /** Safety cap on windows scanned per page when filtering. */
    protected const MAX_WINDOWS = 256;

    /** Chars probed from a line head when testing entry-start. */
    protected const PROBE = 256;

    /**
     * @param  string[]|null  $levels  canonical levels to keep (null = all)
     */
    public function page(
        LogFile $file,
        LogParser $parser,
        ?string $cursor,
        int $limit,
        string $direction = 'backward',
        ?array $levels = null,
        ?string $q = null,
    ): Page {
        $limit = max(1, min($limit, 500));

        if (! $file->readable || $file->size === 0) {
            return new Page([], null, null);
        }

        return $direction === 'forward'
            ? $this->forward($file, $parser, $cursor, $limit, $levels, $q)
            : $this->backward($file, $parser, $cursor, $limit, $levels, $q);
    }

    protected function backward(
        LogFile $file,
        LogParser $parser,
        ?string $cursor,
        int $limit,
        ?array $levels,
        ?string $q,
    ): Page {
        $ctx = $this->context($file);
        $handle = fopen($file->path, 'rb');
        if ($handle === false) {
            return new Page([], null, null);
        }

        try {
            $scanFrom = $this->decodeCursor($cursor) ?? $file->size;
            $matched = [];
            $lastOffset = null;
            $windows = 0;

            while (count($matched) < $limit && $scanFrom > 0 && $windows < self::MAX_WINDOWS) {
                $windows++;
                [$windowStart, $more] = $this->locateWindow($handle, $parser, $scanFrom, $limit);

                $entries = $this->parseSlice($handle, $parser, $ctx, $windowStart, $scanFrom);
                // Newest-first.
                $entries = array_reverse($entries);

                foreach ($entries as $entry) {
                    if (! $this->passes($entry, $levels, $q)) {
                        continue;
                    }
                    $matched[] = $entry;
                    if (count($matched) >= $limit) {
                        $lastOffset = $entry->offset;
                        break 2;
                    }
                }

                if (! $more) {
                    break;
                }
                $scanFrom = $windowStart;
            }

            $next = $lastOffset !== null && $lastOffset > 0
                ? $this->encodeCursor($lastOffset)
                : null;

            return new Page($matched, $next, null);
        } finally {
            fclose($handle);
        }
    }

    protected function forward(
        LogFile $file,
        LogParser $parser,
        ?string $cursor,
        int $limit,
        ?array $levels,
        ?string $q,
    ): Page {
        $ctx = $this->context($file);
        $handle = fopen($file->path, 'rb');
        if ($handle === false) {
            return new Page([], null, null);
        }

        try {
            $from = $this->decodeCursor($cursor) ?? 0;
            $to = (int) min($file->size, $from + self::MAX_WINDOW_BYTES);

            $entries = $this->parseSlice($handle, $parser, $ctx, $from, $to);

            $matched = [];
            $nextOffset = null;
            foreach ($entries as $entry) {
                if (! $this->passes($entry, $levels, $q)) {
                    continue;
                }
                if (count($matched) >= $limit) {
                    $nextOffset = $entry->offset;
                    break;
                }
                $matched[] = $entry;
            }

            return new Page(
                $matched,
                $nextOffset !== null ? $this->encodeCursor($nextOffset) : null,
                null,
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * Read the single entry that begins at $offset (for share / deep links).
     */
    public function entryAt(LogFile $file, LogParser $parser, int $offset): ?LogEntry
    {
        if (! $file->readable || $offset < 0 || $offset >= $file->size) {
            return null;
        }

        $handle = fopen($file->path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $end = $this->nextEntryStart($handle, $parser, $offset, $file->size);
            $entries = $this->parseSlice($handle, $parser, $this->context($file), $offset, $end);

            return $entries[0] ?? null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Offset of the first entry-start line strictly after $from, or EOF.
     */
    protected function nextEntryStart($handle, LogParser $parser, int $from, int $size): int
    {
        $pos = $from;
        $limit = (int) min($size, $from + self::MAX_WINDOW_BYTES);

        fseek($handle, $from);
        $buf = '';

        while ($pos < $limit) {
            $chunk = fread($handle, self::CHUNK);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $buf .= $chunk;
            $pos += strlen($chunk);

            $search = 0;
            while (($nl = strpos($buf, "\n", $search)) !== false) {
                $lineStart = $from + $nl + 1;
                if ($lineStart >= $limit) {
                    return $limit;
                }
                if ($lineStart > $from && $parser->isEntryStart($this->lineAt($buf, $nl + 1))) {
                    // Ensure the candidate line is fully buffered before trusting it.
                    if (strpos($buf, "\n", $nl + 1) !== false || $pos >= $limit) {
                        return $lineStart;
                    }
                }
                $search = $nl + 1;
            }
        }

        return $limit;
    }

    /**
     * Scan backward from $scanFrom to find the start offset of the (up to)
     * $limit newest entries below it.
     *
     * @return array{0: int, 1: bool} [windowStart, moreOlderEntriesExist]
     */
    protected function locateWindow($handle, LogParser $parser, int $scanFrom, int $limit): array
    {
        $winStart = $scanFrom;
        $buf = '';
        $brokeOnCount = false;

        while ($winStart > 0) {
            if ($scanFrom - $winStart >= self::MAX_WINDOW_BYTES) {
                // Pathological (no entry boundaries in 2 MiB) — stop here.
                return [$winStart, true];
            }

            $read = (int) min(self::CHUNK, $winStart);
            $winStart -= $read;
            fseek($handle, $winStart);
            $buf = fread($handle, $read).$buf;

            $starts = $this->entryStarts($buf, $winStart, $scanFrom, $parser);

            if (count($starts) >= $limit) {
                $brokeOnCount = true;
                break;
            }
        }

        if ($brokeOnCount) {
            $starts = $this->entryStarts($buf, $winStart, $scanFrom, $parser);
            $n = count($starts);
            $windowStart = $starts[$n - $limit]; // the $limit-th largest start

            return [$windowStart, true];
        }

        // Reached BOF: include everything from offset 0 so leading orphan lines
        // (a file beginning mid-entry) are not dropped.
        return [0, false];
    }

    /**
     * Absolute offsets (ascending) of entry-start lines within [winStart, scanFrom).
     *
     * @return int[]
     */
    protected function entryStarts(string $buf, int $winStart, int $scanFrom, LogParser $parser): array
    {
        $starts = [];
        $len = strlen($buf);

        // Offset 0 is a line start only when the window reaches the file head.
        if ($winStart === 0 && $scanFrom > 0 && $parser->isEntryStart($this->lineAt($buf, 0))) {
            $starts[] = 0;
        }

        $pos = 0;
        while (($nl = strpos($buf, "\n", $pos)) !== false) {
            $lineStart = $winStart + $nl + 1;
            if ($lineStart >= $scanFrom) {
                break;
            }

            $line = $this->lineAt($buf, $nl + 1);
            if ($parser->isEntryStart($line)) {
                $starts[] = $lineStart;
            }

            $pos = $nl + 1;
        }

        sort($starts);

        return $starts;
    }

    protected function lineAt(string $buf, int $index): string
    {
        $end = strpos($buf, "\n", $index);
        $line = $end === false
            ? substr($buf, $index)
            : substr($buf, $index, $end - $index);

        return substr($line, 0, self::PROBE);
    }

    /**
     * Read [$start, $end) forward and parse it into entries (ascending).
     *
     * @return LogEntry[]
     */
    protected function parseSlice($handle, LogParser $parser, ParseContext $ctx, int $start, int $end): array
    {
        if ($end <= $start) {
            return [];
        }

        fseek($handle, $start);
        $text = (string) fread($handle, $end - $start);

        return iterator_to_array($parser->parse($this->splitLines($text, $start), $ctx), false);
    }

    /**
     * @return iterable<array{offset: int, text: string}>
     */
    protected function splitLines(string $text, int $base): iterable
    {
        $offset = $base;
        $length = strlen($text);
        $cursor = 0;

        while ($cursor < $length) {
            $nl = strpos($text, "\n", $cursor);
            if ($nl === false) {
                yield ['offset' => $offset, 'text' => substr($text, $cursor)];
                break;
            }

            yield ['offset' => $offset, 'text' => substr($text, $cursor, $nl - $cursor)];
            $offset += ($nl - $cursor) + 1;
            $cursor = $nl + 1;
        }
    }

    protected function passes(LogEntry $entry, ?array $levels, ?string $q): bool
    {
        if ($levels !== null && $levels !== [] && ! in_array($entry->level, $levels, true)) {
            return false;
        }

        if ($q !== null && $q !== '') {
            $haystack = $entry->message.' '.($entry->stack ?? '').' '.json_encode($entry->context);
            if (stripos($haystack, $q) === false) {
                return false;
            }
        }

        return true;
    }

    protected function context(LogFile $file): ParseContext
    {
        return new ParseContext($file->sourceKey, $file->id, $file->path);
    }

    protected function decodeCursor(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = Ids::decode($cursor);

        return ctype_digit($decoded) ? (int) $decoded : null;
    }

    protected function encodeCursor(int $offset): string
    {
        return Ids::encode((string) $offset);
    }
}
