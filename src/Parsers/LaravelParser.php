<?php

namespace LogScope\Parsers;

use Carbon\Carbon;
use LogScope\Support\LevelNormalizer;
use LogScope\ValueObjects\LogEntry;
use Throwable;

/**
 * Parses Monolog's default "line" format:
 *
 *   [2024-01-02 03:04:05] production.ERROR: message {context} {extra}
 *       #0 /app/... stack trace continues on following lines
 *
 * Also detects the JSON formatter (one JSON object per line) and falls back to
 * a best-effort message-only entry for malformed lines (kept, never dropped).
 */
class LaravelParser extends AbstractParser
{
    /** Matches the leading "[timestamp] channel.LEVEL:" of a standard line. */
    protected const HEAD = '/^\[(?<ts>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+(?<channel>[^.\s]+)\.(?<level>[A-Za-z]+):\s?(?<rest>.*)$/s';

    /** Matches just the leading bracketed timestamp (cheap entry-start test). */
    protected const TIMESTAMP = '/^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/';

    public function __construct(
        protected LevelNormalizer $levels,
    ) {}

    public function supports(string $sourceKey): bool
    {
        return in_array($sourceKey, ['laravel', 'horizon'], true);
    }

    public function isEntryStart(string $line): bool
    {
        if (preg_match(self::TIMESTAMP, $line) === 1) {
            return true;
        }

        return $this->jsonLine($line) !== null;
    }

    protected function buildEntry(int $offset, array $textLines, ParseContext $context): ?LogEntry
    {
        $first = $textLines[0];

        if (($json = $this->jsonLine($first)) !== null) {
            return $this->fromJson($offset, $json, $context);
        }

        if (preg_match(self::HEAD, $first, $m) === 1) {
            return $this->fromLine($offset, $m, $textLines, $context);
        }

        // Malformed / unrecognised — surface as an unknown-level entry.
        return new LogEntry(
            id: $this->entryId($context, $offset),
            sourceKey: $context->sourceKey,
            file: $context->path,
            fileId: $context->fileId,
            level: 'unknown',
            rawLevel: null,
            message: implode("\n", $textLines),
            context: null,
            channel: null,
            loggedAt: null,
            offset: $offset,
            executionId: null,
        );
    }

    /**
     * @param  array<string, string>  $m  named matches from HEAD
     * @param  string[]  $textLines
     */
    protected function fromLine(int $offset, array $m, array $textLines, ParseContext $context): LogEntry
    {
        $rest = $m['rest'];
        [$message, $jsonContext] = $this->splitTrailingJson($rest);

        $stack = count($textLines) > 1
            ? implode("\n", array_slice($textLines, 1))
            : null;

        return new LogEntry(
            id: $this->entryId($context, $offset),
            sourceKey: $context->sourceKey,
            file: $context->path,
            fileId: $context->fileId,
            level: $this->levels->normalize($m['level']),
            rawLevel: $m['level'],
            message: trim($message),
            context: $jsonContext,
            channel: $m['channel'],
            loggedAt: $this->parseTime($m['ts']),
            offset: $offset,
            executionId: $jsonContext['execution_id'] ?? null,
            stack: $stack,
        );
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function fromJson(int $offset, array $json, ParseContext $context): LogEntry
    {
        $rawLevel = $json['level_name'] ?? $json['level'] ?? null;
        $ctx = is_array($json['context'] ?? null) ? $json['context'] : null;
        $time = $json['datetime'] ?? $json['time'] ?? null;

        return new LogEntry(
            id: $this->entryId($context, $offset),
            sourceKey: $context->sourceKey,
            file: $context->path,
            fileId: $context->fileId,
            level: $this->levels->normalize(is_string($rawLevel) ? $rawLevel : null),
            rawLevel: is_string($rawLevel) ? $rawLevel : null,
            message: (string) ($json['message'] ?? ''),
            context: $ctx,
            channel: isset($json['channel']) ? (string) $json['channel'] : null,
            loggedAt: is_string($time) ? $this->parseTime($time) : null,
            offset: $offset,
            executionId: isset($ctx['execution_id']) ? (string) $ctx['execution_id'] : null,
        );
    }

    /**
     * Pull up to two trailing JSON objects (context, extra) off the end of the
     * message portion. Returns [message, mergedContext|null]. Only extracts
     * when a brace group is space-separated and decodes cleanly, so messages
     * that merely contain braces are left intact.
     *
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    protected function splitTrailingJson(string $rest): array
    {
        $blobs = [];
        $work = rtrim($rest);

        for ($i = 0; $i < 2; $i++) {
            if (! str_ends_with($work, '}')) {
                break;
            }

            $open = $this->matchingBraceStart($work);
            if ($open === null || ($open > 0 && $work[$open - 1] !== ' ')) {
                break;
            }

            $candidate = substr($work, $open);
            $decoded = json_decode($candidate, true);

            if (! is_array($decoded)) {
                break;
            }

            array_unshift($blobs, $decoded); // leftmost first
            $work = rtrim(substr($work, 0, $open));
        }

        if ($blobs === []) {
            return [$rest, null];
        }

        // blobs[0] = context, blobs[1] = extra (when present).
        $context = $blobs[0];
        if (isset($blobs[1]) && $blobs[1] !== []) {
            $context['__extra'] = $blobs[1];
        }

        return [$work, $context === [] ? null : $context];
    }

    /**
     * Index of the '{' that opens the balanced object ending at the last char,
     * or null if unbalanced. String-aware so braces inside JSON strings don't
     * confuse the count.
     */
    protected function matchingBraceStart(string $s): ?int
    {
        $depth = 0;
        $inStr = false;
        $escaped = false;

        for ($i = strlen($s) - 1; $i >= 0; $i--) {
            $c = $s[$i];

            if ($inStr) {
                // Walking backwards, a quote ends the string unless escaped.
                if ($c === '"' && ! $this->isEscaped($s, $i)) {
                    $inStr = false;
                }

                continue;
            }

            if ($c === '"') {
                $inStr = true;
            } elseif ($c === '}') {
                $depth++;
            } elseif ($c === '{') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    protected function isEscaped(string $s, int $pos): bool
    {
        $backslashes = 0;
        for ($i = $pos - 1; $i >= 0 && $s[$i] === '\\'; $i--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }

    /**
     * @return array<string, mixed>|null decoded object if the line is a JSON log record
     */
    protected function jsonLine(string $line): ?array
    {
        $trimmed = trim($line);

        if ($trimmed === '' || $trimmed[0] !== '{' || ! str_ends_with($trimmed, '}')) {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            return null;
        }

        $looksLikeLog = isset($decoded['message'])
            || isset($decoded['level_name'])
            || isset($decoded['level']);

        return $looksLikeLog ? $decoded : null;
    }

    protected function parseTime(string $ts): ?Carbon
    {
        try {
            return Carbon::parse($ts);
        } catch (Throwable) {
            return null;
        }
    }
}
