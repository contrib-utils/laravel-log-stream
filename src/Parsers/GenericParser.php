<?php

namespace LogScope\Parsers;

use LogScope\ValueObjects\LogEntry;

/**
 * Last-resort parser for log formats LogScope doesn't recognise specifically.
 * Each non-indented line becomes an entry; a severity token and a leading
 * timestamp are sniffed best-effort, and nothing is ever dropped.
 */
class GenericParser extends LineParser
{
    /** A severity word appearing anywhere in the line. */
    protected const LEVEL = '/\b(emergency|emerg|alert|critical|crit|fatal|panic|error|err|warning|warn|notice|info|informational|debug|trace|verbose)\b/i';

    /** A leading timestamp in a few common shapes. */
    protected const TIMESTAMP = '#^\[?(\d{4}[-/]\d{2}[-/]\d{2}[ T]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?(?:\s*[+-]\d{2}:?\d{2}|Z)?)#';

    public function supports(string $sourceKey): bool
    {
        return true;
    }

    protected function buildEntry(int $offset, array $textLines, ParseContext $context): ?LogEntry
    {
        $first = $textLines[0];

        $rawLevel = preg_match(self::LEVEL, $first, $m) === 1 ? $m[1] : null;

        $ts = preg_match(self::TIMESTAMP, $first, $tm) === 1
            ? $this->carbon(str_replace(',', '.', $tm[1]))
            : null;

        return $this->makeEntry(
            offset: $offset,
            context: $context,
            rawLevel: $rawLevel,
            message: $first,
            loggedAt: $ts,
            continuations: array_slice($textLines, 1),
        );
    }
}
