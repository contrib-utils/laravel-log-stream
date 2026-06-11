<?php

namespace LogScope\Tests\Unit;

use LogScope\Parsers\LaravelParser;
use LogScope\Parsers\ParseContext;
use LogScope\Tests\TestCase;
use LogScope\ValueObjects\LogEntry;
use PHPUnit\Framework\Attributes\Test;

class LaravelParserTest extends TestCase
{
    protected function parser(): LaravelParser
    {
        return $this->app->make(LaravelParser::class);
    }

    protected function context(): ParseContext
    {
        return new ParseContext('laravel', 'fid', '/tmp/laravel.log');
    }

    /**
     * @param  string[]  $rawLines
     * @return LogEntry[]
     */
    protected function parse(array $rawLines): array
    {
        $offset = 0;
        $lines = [];
        foreach ($rawLines as $text) {
            $lines[] = ['offset' => $offset, 'text' => $text];
            $offset += strlen($text) + 1;
        }

        return iterator_to_array($this->parser()->parse($lines, $this->context()), false);
    }

    #[Test]
    public function parses_a_standard_single_line_entry(): void
    {
        [$entry] = $this->parse(['[2024-01-02 03:04:05] production.ERROR: Something broke']);

        $this->assertSame('error', $entry->level);
        $this->assertSame('ERROR', $entry->rawLevel);
        $this->assertSame('production', $entry->channel);
        $this->assertSame('Something broke', $entry->message);
        $this->assertSame('2024-01-02', $entry->loggedAt?->toDateString());
        $this->assertSame(0, $entry->offset);
    }

    #[Test]
    public function decodes_json_context_and_execution_id(): void
    {
        [$entry] = $this->parse([
            '[2024-01-02 03:04:05] local.INFO: User logged in {"user_id":5,"execution_id":"abc-123"}',
        ]);

        $this->assertSame('User logged in', $entry->message);
        $this->assertSame(5, $entry->context['user_id']);
        $this->assertSame('abc-123', $entry->executionId);
    }

    #[Test]
    public function groups_multiline_stack_traces_into_the_entry_above(): void
    {
        $entries = $this->parse([
            '[2024-01-02 03:04:05] production.ERROR: Boom',
            '[stacktrace]',
            '#0 /app/Foo.php(10): bar()',
            '#1 {main}',
            '[2024-01-02 03:04:06] production.INFO: Recovered',
        ]);

        $this->assertCount(2, $entries);
        $this->assertSame('Boom', $entries[0]->message);
        $this->assertStringContainsString('#0 /app/Foo.php', $entries[0]->stack);
        $this->assertStringContainsString('#1 {main}', $entries[0]->stack);
        $this->assertSame('Recovered', $entries[1]->message);
        $this->assertNull($entries[1]->stack);
    }

    #[Test]
    public function handles_microsecond_timestamps_and_mixed_levels(): void
    {
        $entries = $this->parse([
            '[2024-01-02 03:04:05.123456] production.WARNING: careful',
            '[2024-01-02 03:04:06.000001] production.DEBUG: noisy',
        ]);

        $this->assertSame('warning', $entries[0]->level);
        $this->assertSame('debug', $entries[1]->level);
        $this->assertNotNull($entries[0]->loggedAt);
    }

    #[Test]
    public function keeps_malformed_lines_as_unknown_entries(): void
    {
        $entries = $this->parse(['this is not a real log line at all']);

        $this->assertCount(1, $entries);
        $this->assertSame('unknown', $entries[0]->level);
        $this->assertStringContainsString('not a real log line', $entries[0]->message);
    }

    #[Test]
    public function parses_the_json_formatter(): void
    {
        [$entry] = $this->parse([
            '{"message":"Json log","level_name":"ERROR","channel":"production","datetime":"2024-01-02T03:04:05+00:00","context":{"execution_id":"xyz"}}',
        ]);

        $this->assertSame('Json log', $entry->message);
        $this->assertSame('error', $entry->level);
        $this->assertSame('production', $entry->channel);
        $this->assertSame('xyz', $entry->executionId);
        $this->assertSame('2024-01-02', $entry->loggedAt?->toDateString());
    }

    #[Test]
    public function does_not_mistake_braces_inside_a_message_for_context(): void
    {
        [$entry] = $this->parse([
            '[2024-01-02 03:04:05] production.ERROR: payload was {invalid json here',
        ]);

        $this->assertStringContainsString('{invalid json here', $entry->message);
        $this->assertNull($entry->context);
    }
}
