<?php

namespace LogScope\Tests\Feature;

use LogScope\Support\Ids;
use LogScope\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EntriesApiTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/logscope_api_'.uniqid();
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

    protected function seedLog(string $name = 'laravel.log'): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, implode("\n", [
            '[2024-01-01 00:00:00] production.INFO: alpha',
            '[2024-01-01 00:00:01] production.ERROR: beta failed',
            '[2024-01-01 00:00:02] production.WARNING: gamma',
        ])."\n");

        return $path;
    }

    protected function auth(): array
    {
        return $this->basic('admin', 'secret');
    }

    #[Test]
    public function lists_enabled_sources(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/logscope/api/sources')
            ->assertOk()
            ->assertJsonFragment(['key' => 'laravel', 'label' => 'Laravel']);
    }

    #[Test]
    public function lists_files_for_a_source_newest_first(): void
    {
        $this->seedLog('laravel.log');

        $this->withHeaders($this->auth())
            ->getJson('/logscope/api/files?source=laravel')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'size', 'mtime', 'readable']], 'meta'])
            ->assertJsonFragment(['name' => 'laravel.log']);
    }

    #[Test]
    public function returns_paginated_entries_newest_first(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $res = $this->withHeaders($this->auth())
            ->getJson("/logscope/api/files/{$fileId}/entries?per_page=2")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['next_cursor', 'prev_cursor']]);

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('gamma', $data[0]['message']);
        $this->assertSame('beta failed', $data[1]['message']);
        $this->assertNotNull($res->json('meta.next_cursor'));
    }

    #[Test]
    public function filters_entries_by_level(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $res = $this->withHeaders($this->auth())
            ->getJson("/logscope/api/files/{$fileId}/entries?level=error")
            ->assertOk();

        $data = $res->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('error', $data[0]['level']);
    }

    #[Test]
    public function searches_entries_by_substring(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $res = $this->withHeaders($this->auth())
            ->getJson("/logscope/api/files/{$fileId}/entries?q=gamma")
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('gamma', $res->json('data.0.message'));
    }

    #[Test]
    public function resolves_a_single_entry_for_share_links(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $entryId = $this->withHeaders($this->auth())
            ->getJson("/logscope/api/files/{$fileId}/entries")
            ->json('data.0.id');

        $this->withHeaders($this->auth())
            ->getJson("/logscope/api/entries/{$entryId}")
            ->assertOk()
            ->assertJsonPath('data.message', 'gamma');
    }

    #[Test]
    public function rejects_file_ids_that_escape_the_source_roots(): void
    {
        $this->seedLog();

        // A perfectly valid encoding of a path outside any allow-listed root.
        $evil = Ids::fileId('/etc/passwd');

        $this->withHeaders($this->auth())
            ->getJson("/logscope/api/files/{$evil}/entries")
            ->assertNotFound();
    }

    #[Test]
    public function rejects_traversal_attempts_into_the_source_root(): void
    {
        $this->seedLog();

        $traversal = Ids::fileId($this->dir.'/../../../../etc/passwd');

        $this->withHeaders($this->auth())
            ->getJson("/logscope/api/files/{$traversal}/entries")
            ->assertNotFound();
    }

    #[Test]
    public function entries_endpoint_requires_authorization(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $this->getJson("/logscope/api/files/{$fileId}/entries")->assertStatus(401);
    }
}
