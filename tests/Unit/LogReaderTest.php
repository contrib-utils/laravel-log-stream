<?php

namespace LogScope\Tests\Unit;

use LogScope\Parsers\LaravelParser;
use LogScope\Sources\LogReader;
use LogScope\Support\Ids;
use LogScope\Tests\TestCase;
use LogScope\ValueObjects\LogFile;
use PHPUnit\Framework\Attributes\Test;

class LogReaderTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'logscope_');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    protected function write(string $contents): LogFile
    {
        file_put_contents($this->path, $contents);
        clearstatcache();

        return new LogFile(
            id: Ids::fileId($this->path),
            sourceKey: 'laravel',
            path: $this->path,
            name: basename($this->path),
            size: strlen($contents),
            mtime: time(),
            readable: true,
        );
    }

    protected function reader(): LogReader
    {
        return $this->app->make(LogReader::class);
    }

    protected function parser(): LaravelParser
    {
        return $this->app->make(LaravelParser::class);
    }

    /** Build N standard entries, oldest first, with the given levels cycling. */
    protected function buildLog(int $count, array $levels = ['INFO']): string
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $level = $levels[$i % count($levels)];
            $ts = sprintf('2024-01-01 00:%02d:%02d', intdiv($i, 60), $i % 60);
            $lines[] = "[{$ts}] production.{$level}: entry number {$i}";
        }

        return implode("\n", $lines)."\n";
    }

    #[Test]
    public function returns_newest_entries_first(): void
    {
        $file = $this->write($this->buildLog(5));

        $page = $this->reader()->page($file, $this->parser(), null, 2);

        $this->assertCount(2, $page->data);
        $this->assertSame('entry number 4', $page->data[0]->message);
        $this->assertSame('entry number 3', $page->data[1]->message);
        $this->assertNotNull($page->nextCursor);
    }

    #[Test]
    public function paginates_backward_without_gaps_or_overlap(): void
    {
        $file = $this->write($this->buildLog(5));
        $reader = $this->reader();

        $seen = [];
        $cursor = null;
        do {
            $page = $reader->page($file, $this->parser(), $cursor, 2);
            foreach ($page->data as $e) {
                $seen[] = $e->message;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $this->assertSame([
            'entry number 4', 'entry number 3',
            'entry number 2', 'entry number 1',
            'entry number 0',
        ], $seen);
    }

    #[Test]
    public function null_cursor_marks_the_end_of_the_file(): void
    {
        $file = $this->write($this->buildLog(2));

        $page = $this->reader()->page($file, $this->parser(), null, 10);

        $this->assertCount(2, $page->data);
        $this->assertNull($page->nextCursor);
    }

    #[Test]
    public function level_filter_spans_pagination(): void
    {
        // 10 entries alternating ERROR / INFO → 5 errors.
        $file = $this->write($this->buildLog(10, ['ERROR', 'INFO']));

        $errors = [];
        $cursor = null;
        do {
            $page = $this->reader()->page($file, $this->parser(), $cursor, 2, 'backward', ['error']);
            foreach ($page->data as $e) {
                $this->assertSame('error', $e->level);
                $errors[] = $e->message;
            }
            $cursor = $page->nextCursor;
        } while ($cursor !== null);

        $this->assertCount(5, $errors);
        $this->assertSame('entry number 8', $errors[0]); // newest error first
    }

    #[Test]
    public function substring_search_matches_message(): void
    {
        $file = $this->write($this->buildLog(20));

        $page = $this->reader()->page($file, $this->parser(), null, 50, 'backward', null, 'number 7');

        $this->assertCount(1, $page->data);
        $this->assertSame('entry number 7', $page->data[0]->message);
    }

    #[Test]
    public function multiline_entries_keep_their_stack_and_boundaries(): void
    {
        $contents = implode("\n", [
            '[2024-01-01 00:00:00] production.INFO: first',
            '[2024-01-01 00:00:01] production.ERROR: exploded',
            '#0 /app/Foo.php(10): bar()',
            '#1 {main}',
            '[2024-01-01 00:00:02] production.INFO: last',
        ])."\n";
        $file = $this->write($contents);

        $page = $this->reader()->page($file, $this->parser(), null, 10);

        $this->assertCount(3, $page->data);
        $this->assertSame('last', $page->data[0]->message);
        $this->assertSame('exploded', $page->data[1]->message);
        $this->assertStringContainsString('#0 /app/Foo.php', $page->data[1]->stack);
        $this->assertSame('first', $page->data[2]->message);
    }

    #[Test]
    public function reads_a_single_entry_by_offset_for_deep_links(): void
    {
        $file = $this->write($this->buildLog(5));

        // Grab an entry's offset from a normal page, then resolve it directly.
        $page = $this->reader()->page($file, $this->parser(), null, 5);
        $target = $page->data[2]; // 'entry number 2'

        $entry = $this->reader()->entryAt($file, $this->parser(), $target->offset);

        $this->assertNotNull($entry);
        $this->assertSame('entry number 2', $entry->message);
    }

    #[Test]
    public function deep_cursor_jump_returns_correct_slice_on_a_large_file(): void
    {
        $file = $this->write($this->buildLog(1000));
        $reader = $this->reader();

        // Page through to the 3rd page and confirm contiguous, newest-first.
        $cursor = null;
        $all = [];
        for ($i = 0; $i < 3; $i++) {
            $page = $reader->page($file, $this->parser(), $cursor, 10);
            foreach ($page->data as $e) {
                $all[] = $e->message;
            }
            $cursor = $page->nextCursor;
        }

        $this->assertSame('entry number 999', $all[0]);
        $this->assertSame('entry number 970', $all[29]);
        $this->assertCount(30, $all);
    }
}
