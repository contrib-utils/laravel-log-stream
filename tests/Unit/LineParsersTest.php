<?php

namespace LogScope\Tests\Unit;

use LogScope\Parsers\ApacheParser;
use LogScope\Parsers\GenericParser;
use LogScope\Parsers\LineParser;
use LogScope\Parsers\NginxParser;
use LogScope\Parsers\ParseContext;
use LogScope\Parsers\PostgresParser;
use LogScope\Parsers\RedisParser;
use LogScope\Parsers\SupervisorParser;
use LogScope\Tests\TestCase;
use LogScope\ValueObjects\LogEntry;
use PHPUnit\Framework\Attributes\Test;

class LineParsersTest extends TestCase
{
    /**
     * @param  string[]  $rawLines
     * @return LogEntry[]
     */
    protected function parse(LineParser $parser, array $rawLines, string $source = 'generic'): array
    {
        $offset = 0;
        $lines = [];
        foreach ($rawLines as $text) {
            $lines[] = ['offset' => $offset, 'text' => $text];
            $offset += strlen($text) + 1;
        }

        $ctx = new ParseContext($source, 'fid', '/tmp/'.$source.'.log');

        return iterator_to_array($parser->parse($lines, $ctx), false);
    }

    #[Test]
    public function nginx_error_and_access_lines(): void
    {
        $p = $this->app->make(NginxParser::class);

        [$err, $ok, $bad] = $this->parse($p, [
            '2024/04/01 12:34:56 [error] 1234#0: *5 connect() failed',
            '1.2.3.4 - - [01/Apr/2024:12:34:56 +0000] "GET /a HTTP/1.1" 200 12 "-" "curl"',
            '9.9.9.9 - - [01/Apr/2024:12:34:57 +0000] "GET /b HTTP/1.1" 502 0 "-" "curl"',
        ], 'nginx');

        $this->assertSame('error', $err->level);
        $this->assertSame('error', $err->channel);
        $this->assertStringContainsString('connect() failed', $err->message);
        $this->assertSame('2024-04-01', $err->loggedAt?->toDateString());

        $this->assertSame('info', $ok->level);       // 200
        $this->assertSame('error', $bad->level);      // 502
        $this->assertSame(502, $bad->context['status']);
    }

    #[Test]
    public function apache_error_with_module_level_and_pid(): void
    {
        $p = $this->app->make(ApacheParser::class);

        [$entry] = $this->parse($p, [
            '[Wed Oct 11 14:32:52.123456 2024] [core:error] [pid 1234] AH00037: message',
        ], 'apache');

        $this->assertSame('error', $entry->level);
        $this->assertStringContainsString('AH00037: message', $entry->message);
        $this->assertSame('2024-10-11', $entry->loggedAt?->toDateString());
    }

    #[Test]
    public function redis_symbol_levels(): void
    {
        $p = $this->app->make(RedisParser::class);

        [$notice, $warning] = $this->parse($p, [
            '1234:M 01 Apr 2024 12:34:56.789 * Ready to accept connections',
            '1234:M 01 Apr 2024 12:34:57.000 # WARNING overcommit_memory is set to 0',
        ], 'redis');

        $this->assertSame('notice', $notice->level);
        $this->assertSame('M', $notice->channel);
        $this->assertSame('warning', $warning->level);
        $this->assertSame('2024-04-01', $notice->loggedAt?->toDateString());
    }

    #[Test]
    public function postgres_levels_and_aliases(): void
    {
        $p = $this->app->make(PostgresParser::class);

        [$log, $error] = $this->parse($p, [
            '2024-04-01 12:34:56.789 UTC [1234] LOG:  database system is ready',
            '2024-04-01 12:34:57.000 UTC [1234] ERROR:  relation "x" does not exist',
        ], 'postgres');

        $this->assertSame('info', $log->level);   // LOG -> info (alias)
        $this->assertSame('error', $error->level);
        $this->assertStringContainsString('database system is ready', $log->message);
        $this->assertSame('2024-04-01', $log->loggedAt?->toDateString());
    }

    #[Test]
    public function supervisor_levels_and_aliases(): void
    {
        $p = $this->app->make(SupervisorParser::class);

        [$info, $err] = $this->parse($p, [
            "2024-04-01 12:34:56,789 INFO spawned: 'worker' with pid 1234",
            '2024-04-01 12:34:57,000 ERRO gave up: worker entered FATAL state',
        ], 'supervisor');

        $this->assertSame('info', $info->level);
        $this->assertSame('error', $err->level);   // ERRO -> error (alias)
        $this->assertSame('2024-04-01', $info->loggedAt?->toDateString());
    }

    #[Test]
    public function generic_sniffs_level_and_keeps_everything(): void
    {
        $p = $this->app->make(GenericParser::class);

        [$warn, $plain] = $this->parse($p, [
            '2024-04-01 12:34:56 WARNING disk almost full',
            'just some unstructured output',
        ]);

        $this->assertSame('warning', $warn->level);
        $this->assertSame('2024-04-01', $warn->loggedAt?->toDateString());
        $this->assertSame('unknown', $plain->level);
        $this->assertStringContainsString('unstructured output', $plain->message);
    }

    #[Test]
    public function line_parsers_fold_indented_continuations(): void
    {
        $p = $this->app->make(PostgresParser::class);

        [$entry] = $this->parse($p, [
            '2024-04-01 12:34:56.789 UTC [1234] ERROR:  syntax error',
            "\tDETAIL:  near \"slect\"",
            "\tSTATEMENT:  slect 1",
        ], 'postgres');

        $this->assertSame('error', $entry->level);
        $this->assertStringContainsString('DETAIL', $entry->stack);
        $this->assertStringContainsString('STATEMENT', $entry->stack);
    }
}
