<?php

namespace LogScope\Parsers;

use LogScope\ValueObjects\LogEntry;

/**
 * Redis server log.
 *
 *   1234:M 01 Apr 2024 12:34:56.789 * Ready to accept connections
 *
 * After "pid:role" comes a level symbol: '.' debug, '-' verbose, '*' notice,
 * '#' warning. The role letter (X sentinel, C child, S slave, M master) is
 * kept as the channel.
 */
class RedisParser extends LineParser
{
    protected const LINE = '/^(?<pid>\d+):(?<role>[XCSM]) (?<ts>\d{2} \w{3} \d{4} \d{2}:\d{2}:\d{2}\.\d+) (?<sym>[.\-*#]) (?<msg>.*)$/s';

    /** @var array<string, string> */
    protected const SYMBOLS = [
        '.' => 'debug',
        '-' => 'verbose',
        '*' => 'notice',
        '#' => 'warning',
    ];

    public function supports(string $sourceKey): bool
    {
        return $sourceKey === 'redis';
    }

    protected function buildEntry(int $offset, array $textLines, ParseContext $context): ?LogEntry
    {
        $first = $textLines[0];
        $continuations = array_slice($textLines, 1);

        if (preg_match(self::LINE, $first, $m) === 1) {
            return $this->makeEntry(
                offset: $offset,
                context: $context,
                rawLevel: self::SYMBOLS[$m['sym']] ?? null,
                message: $m['msg'],
                loggedAt: $this->carbon($m['ts'], 'd M Y H:i:s.v'),
                channel: $m['role'],
                continuations: $continuations,
            );
        }

        return $this->makeEntry($offset, $context, null, $first, continuations: $continuations);
    }
}
