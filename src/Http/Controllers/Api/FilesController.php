<?php

namespace LogScope\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogScope\Sources\LogFileRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FilesController
{
    public function __construct(
        protected LogFileRepository $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $source = (string) $request->query('source', 'laravel');

        $files = array_map(
            fn ($file) => $file->toArray(),
            $this->repository->filesFor($source),
        );

        return response()->json([
            'data' => $files,
            'meta' => ['source' => $source, 'count' => count($files)],
        ]);
    }

    /**
     * GET /api/files/{fileId}/download — stream the raw log file.
     * Gated by the logscope.file-ops middleware.
     */
    public function download(string $fileId): BinaryFileResponse
    {
        $file = $this->resolve($fileId);
        abort_unless($file->readable, 403, 'Log file is not readable.');

        return response()->download($file->path, $file->name, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * POST /api/files/{fileId}/clear — truncate the file in place.
     */
    public function clear(string $fileId): JsonResponse
    {
        $file = $this->resolve($fileId);
        abort_unless(is_writable($file->path), 422, 'Log file is not writable.');

        if (@file_put_contents($file->path, '') === false) {
            abort(422, 'Could not clear the log file.');
        }

        return response()->json(['data' => ['id' => $file->id, 'size' => 0, 'cleared' => true]]);
    }

    /**
     * DELETE /api/files/{fileId} — remove the file from disk.
     */
    public function destroy(string $fileId): JsonResponse
    {
        $file = $this->resolve($fileId);
        abort_unless(is_writable(dirname($file->path)), 422, 'Log file cannot be deleted (directory not writable).');

        if (! @unlink($file->path)) {
            abort(422, 'Could not delete the log file.');
        }

        return response()->json(['data' => ['id' => $file->id, 'deleted' => true]]);
    }

    /**
     * Resolve and validate a client-supplied file id, or 404. The repository
     * realpath-checks the id against the allow-listed source roots, so this is
     * the only gate file mutations need against path traversal.
     */
    protected function resolve(string $fileId): \LogScope\ValueObjects\LogFile
    {
        $file = $this->repository->resolveFileId($fileId);
        abort_if($file === null, 404, 'Log file not found.');

        return $file;
    }
}
