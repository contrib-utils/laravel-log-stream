<?php

namespace LogScope\Parsers;

use LogScope\ValueObjects\LogEntry;

/**
 * Nginx error and access logs.
 *
 *   error:  2024/04/01 12:34:56 [error] 1234#0: *5 message
 *   access: 1.2.3.4 - - [01/Apr/2024:12:34:56 +0000] "GET /x HTTP/1.1" 502 12 "-" "curl"
 *
 * Access lines carry no level word, so severity is derived from the HTTP
 * status code (5xx -> error, 4xx -> warning, else info).
 */
class NginxParser extends LineParser
{
    protected const ERROR = '#^(?<ts>\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2}) \[(?<level>\w+)\] (?<msg>.*)$#s';

    protected const ACCESS = '#^(?<ip>\S+) \S+ \S+ \[(?<ts>[^\]]+)\] "(?<req>[^"]*)" (?<status>\d{3}) (?<size>\S+)#';

    public function supports(string $sourceKey): bool
    {
        return $sourceKey === 'nginx';
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
                message: $m['msg'],
                loggedAt: $this->carbon($m['ts'], 'Y/m/d H:i:s'),
                channel: 'error',
                continuations: $continuations,
            );
        }

        if (preg_match(self::ACCESS, $first, $m) === 1) {
            $status = (int) $m['status'];

            return $this->makeEntry(
                offset: $offset,
                context: $context,
                rawLevel: $this->statusLevel($status),
                message: trim($m['req']).' — '.$status,
                loggedAt: $this->carbon($m['ts'], 'd/M/Y:H:i:s O'),
                channel: 'access',
                contextData: ['ip' => $m['ip'], 'status' => $status, 'request' => $m['req']],
                continuations: $continuations,
            );
        }

        return $this->makeEntry($offset, $context, null, $first, continuations: $continuations);
    }

    protected function statusLevel(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };
    }
}
