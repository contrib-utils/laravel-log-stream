<?php

namespace LogScope\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogScope\Sources\LogFileRepository;

class FilesController
{
    public function index(Request $request, LogFileRepository $repository): JsonResponse
    {
        $source = (string) $request->query('source', 'laravel');

        $files = array_map(
            fn ($file) => $file->toArray(),
            $repository->filesFor($source),
        );

        return response()->json([
            'data' => $files,
            'meta' => ['source' => $source, 'count' => count($files)],
        ]);
    }
}
