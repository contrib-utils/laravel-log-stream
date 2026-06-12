<?php

namespace LogScope\Parsers;

use LogScope\ValueObjects\LogEntry;

/**
 * PostgreSQL server log (default stderr line format).
 *
 *   2024-04-01 12:34:56.789 UTC [1234] LOG:  database system is ready
 *   2024-04-01 12:34:56.789 UTC [1234] ERROR:  relation "x" does not exist
 *
 * The token before the first colon is the severity (LOG, ERROR, FATAL, PANIC,
 * WARNING, NOTICE, INFO, DEBUG, DETAIL, HINT, STATEMENT, CONTEXT).
 */
class PostgresParser extends LineParser
{
    protected const LINE = '/^(?<ts>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?)(?: (?<tz>[A-Z]{2,5}))? \[(?<pid>\d+)\] (?<level>[A-Z]+):\s+(?<msg>.*)$/s';

    public function supports(string $sourceKey): bool
    {
        return $sourceKey === 'postgres';
    }

    protected function buildEntry(int $offset, array $textLines, ParseContext $context): ?LogEntry
    {
        $first = $textLines[0];
        $continuations = array_slice($textLines, 1);

        if (preg_match(self::LINE, $first, $m) === 1) {
            $ts = $m['ts'].(isset($m['tz']) && $m['tz'] !== '' ? ' '.$m['tz'] : '');

            return $this->makeEntry(
                offset: $offset,
                context: $context,
                rawLevel: $m['level'],
                message: $m['msg'],
                loggedAt: $this->carbon($ts),
                channel: 'pid '.$m['pid'],
                continuations: $continuations,
            );
        }

        return $this->makeEntry($offset, $context, null, $first, continuations: $continuations);
    }
}
