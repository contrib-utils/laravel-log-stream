<?php

namespace LogScope\Tests\Feature;

use LogScope\Support\Ids;
use LogScope\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileOperationsApiTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/logscope_fileops_'.uniqid();
        mkdir($this->dir, 0777, true);

        config([
            'logscope.auth.user' => 'admin',
            'logscope.auth.password' => 'secret',
            'logscope.allow_file_operations' => true,
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
        file_put_contents($path, "[2024-01-01 00:00:00] production.INFO: hello\n");

        return $path;
    }

    protected function auth(): array
    {
        return $this->basic('admin', 'secret');
    }

    #[Test]
    public function downloads_the_raw_file(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $res = $this->withHeaders($this->auth())
            ->get("/logscope/api/files/{$fileId}/download")
            ->assertOk();

        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
        $this->assertStringContainsString('hello', $res->streamedContent());
    }

    #[Test]
    public function clears_the_file_in_place(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $this->withHeaders($this->auth())
            ->postJson("/logscope/api/files/{$fileId}/clear")
            ->assertOk()
            ->assertJsonPath('data.cleared', true);

        $this->assertSame('', file_get_contents($path));
        $this->assertFileExists($path);
    }

    #[Test]
    public function deletes_the_file(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $this->withHeaders($this->auth())
            ->deleteJson("/logscope/api/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertFileDoesNotExist($path);
    }

    #[Test]
    public function operations_are_forbidden_when_the_switch_is_off(): void
    {
        config(['logscope.allow_file_operations' => false]);

        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $this->withHeaders($this->auth())->get("/logscope/api/files/{$fileId}/download")->assertForbidden();
        $this->withHeaders($this->auth())->postJson("/logscope/api/files/{$fileId}/clear")->assertForbidden();
        $this->withHeaders($this->auth())->deleteJson("/logscope/api/files/{$fileId}")->assertForbidden();

        // The file is untouched.
        $this->assertFileExists($path);
        $this->assertNotSame('', file_get_contents($path));
    }

    #[Test]
    public function operations_require_authorization(): void
    {
        $path = $this->seedLog();
        $fileId = Ids::fileId($path);

        $this->getJson("/logscope/api/files/{$fileId}/download")->assertStatus(401);
        $this->postJson("/logscope/api/files/{$fileId}/clear")->assertStatus(401);
        $this->deleteJson("/logscope/api/files/{$fileId}")->assertStatus(401);
    }

    #[Test]
    public function rejects_ids_that_escape_the_source_roots(): void
    {
        $this->seedLog();
        $evil = Ids::fileId('/etc/passwd');

        $this->withHeaders($this->auth())->postJson("/logscope/api/files/{$evil}/clear")->assertNotFound();
        $this->withHeaders($this->auth())->deleteJson("/logscope/api/files/{$evil}")->assertNotFound();
    }
}
