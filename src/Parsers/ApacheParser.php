<?php

namespace LogScope\Parsers;

use LogScope\ValueObjects\LogEntry;

/**
 * Apache error and access logs.
 *
 *   error:  [Wed Oct 11 14:32:52.123456 2024] [core:error] [pid 1234] message
 *           [Wed Oct 11 14:32:52 2024] [error] message      (older format)
 *   access: 1.2.3.4 - - [11/Oct/2024:14:32:52 +0000] "GET /x HTTP/1.1" 500 12
 */
class ApacheParser extends LineParser
{
    protected const ERROR = '#^\[(?<ts>[A-Za-z]{3} [A-Za-z]{3} \d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)? \d{4})\] \[(?:[\w-]+:)?(?<level>\w+)\](?: \[pid \d+(?::tid \d+)?\])?\s*(?<msg>.*)$#s';

    protected const ACCESS = '#^(?<ip>\S+) \S+ \S+ \[(?<ts>[^\]]+)\] "(?<req>[^"]*)" (?<status>\d{3}) (?<size>\S+)#';

    public function supports(string $sourceKey): bool
    {
        return $sourceKey === 'apache';
    }

    protected function buildEntry(int $offset, array $textLines, ParseContext $context): ?LogEntry
    {
        $first = $textLines[0];
        $continuations = array_slice($textLines, 1);

        if (preg_match(self::ERROR, $first, $m) === 1) {
            return $this->makeEntry(
                offset: $offset,
                context: $context,
                rawLevel: $m['level'],
                message: trim($m['msg']),
                loggedAt: $this->apacheTime($m['ts']),
                channel: 'error',
                continuations: $continuations,
            );
        }

        if (preg_match(self::ACCESS, $first, $m) === 1) {
            $status = (int) $m['status'];

            return $this->makeEntry(
                offset: $offset,
                context: $context,
                rawLevel: match (true) {
                    $status >= 500 => 'error',
                    $status >= 400 => 'warning',
                    default => 'info',
                },
                message: trim($m['req']).' — '.$status,
                loggedAt: $this->carbon($m['ts'], 'd/M/Y:H:i:s O'),
                channel: 'access',
                contextData: ['ip' => $m['ip'], 'status' => $status, 'request' => $m['req']],
                continuations: $continuations,
            );
        }

        return $this->makeEntry($offset, $context, null, $first, continuations: $continuations);
    }

    /**
     * Apache's error-log timestamp ("Wed Oct 11 14:32:52.123456 2024") defeats
     * Carbon::parse, so try explicit formats — with and without microseconds,
     * collapsing the space-padded single-digit day first.
     */
    protected function apacheTime(string $ts): ?\Carbon\CarbonInterface
    {
        // Drop the leading weekday name — it is redundant, and PHP's `D` token
        // would otherwise *shift* the date if the name disagrees with the date.
        $ts = preg_replace('/^\s*[A-Za-z]{3}\s+/', '', preg_replace('/\s+/', ' ', trim($ts)));

        return $this->carbon($ts, 'M j H:i:s.u Y') ?? $this->carbon($ts, 'M j H:i:s Y');
    }
}
