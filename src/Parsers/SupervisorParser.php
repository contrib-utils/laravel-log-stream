<?php

namespace LogScope\Parsers;

use LogScope\ValueObjects\LogEntry;

/**
 * Supervisord log.
 *
 *   2024-04-01 12:34:56,789 INFO spawned: 'worker' with pid 1234
 *
 * Supervisor's level tokens (CRIT, ERRO, WARN, INFO, DEBG, TRAC, BLAT) are
 * mapped to canonical levels via config aliases.
 */
class SupervisorParser extends LineParser
{
    protected const LINE = '/^(?<ts>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+ (?<level>[A-Z]{3,4}) (?<msg>.*)$/s';

    public function supports(string $sourceKey): bool
    {
        return $sourceKey === 'supervisor';
    }

    protected function buildEntry(int $offset, array $textLines, ParseContext $context): ?LogEntry
    {
        $first = $textLines[0];
        $continuations = array_slice($textLines, 1);

        if (preg_match(self::LINE, $first, $m) === 1) {
            return $this->makeEntry(
                offset: $offset,
                context: $context,
                rawLevel: $m['level'],
                message: $m['msg'],
                loggedAt: $this->carbon(str_replace(',', '.', $m['ts'])),
                continuations: $continuations,
            );
        }

        return $this->makeEntry($offset, $context, null, $first, continuations: $continuations);
    }
}
