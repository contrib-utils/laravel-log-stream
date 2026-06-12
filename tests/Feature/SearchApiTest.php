<?php

namespace LogScope\Tests\Feature;

use LogScope\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchApiTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/logscope_search_'.uniqid();
        mkdir($this->dir, 0777, true);

        config([
            'logscope.auth.user' => 'admin',
            'logscope.auth.password' => 'secret',
            'logscope.sources.laravel.paths' => [$this->dir.'/*.log'],
        ]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    protected function seedFile(string $name, array $lines, int $mtime): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, implode("\n", $lines)."\n");
        touch($path, $mtime);

        return $path;
    }

    protected function auth(): array
    {
        return $this->basic('admin', 'secret');
    }

    #[Test]
    public function searches_across_every_file_in_a_source(): void
    {
        $this->seedFile('older.log', [
            '[2024-01-01 00:00:00] production.INFO: needle in the old file',
            '[2024-01-01 00:00:01] production.INFO: unrelated',
        ], 1_000);

        $this->seedFile('newer.log', [
            '[2024-01-02 00:00:00] production.ERROR: needle in the new file',
        ], 2_000);

        $res = $this->withHeaders($this->auth())
            ->getJson('/logscope/api/search?source=laravel&q=needle')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'message', 'file_name']], 'meta' => ['next_cursor', 'source']]);

        $data = $res->json('data');
        $this->assertCount(2, $data);
        // Newest file first.
        $this->assertSame('newer.log', $data[0]['file_name']);
        $this->assertSame('older.log', $data[1]['file_name']);
        $this->assertStringContainsString('needle', $data[0]['message']);
    }

    #[Test]
    public function paginates_across_the_file_boundary(): void
    {
        $this->seedFile('older.log', [
            '[2024-01-01 00:00:00] production.INFO: hit one',
        ], 1_000);

        $this->seedFile('newer.log', [
            '[2024-01-02 00:00:00] production.INFO: hit two',
        ], 2_000);

        // One per page forces the cursor to walk from newer.log into older.log.
        $first = $this->withHeaders($this->auth())
            ->getJson('/logscope/api/search?source=laravel&q=hit&per_page=1')
            ->assertOk();

        $this->assertCount(1, $first->json('data'));
        $this->assertSame('newer.log', $first->json('data.0.file_name'));
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $second = $this->withHeaders($this->auth())
            ->getJson('/logscope/api/search?source=laravel&q=hit&per_page=1&cursor='.urlencode($cursor))
            ->assertOk();

        $this->assertCount(1, $second->json('data'));
        $this->assertSame('older.log', $second->json('data.0.file_name'));
    }

    #[Test]
    public function honours_the_level_filter(): void
    {
        $this->seedFile('app.log', [
            '[2024-01-01 00:00:00] production.INFO: keep info',
            '[2024-01-01 00:00:01] production.ERROR: keep error',
        ], 1_000);

        $res = $this->withHeaders($this->auth())
            ->getJson('/logscope/api/search?source=laravel&q=keep&level=error')
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('error', $res->json('data.0.level'));
    }

    #[Test]
    public function blank_query_returns_no_rows(): void
    {
        $this->seedFile('app.log', ['[2024-01-01 00:00:00] production.INFO: x'], 1_000);

        $this->withHeaders($this->auth())
            ->getJson('/logscope/api/search?source=laravel&q=')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function search_requires_authorization(): void
    {
        $this->getJson('/logscope/api/search?source=laravel&q=x')->assertStatus(401);
    }
}
