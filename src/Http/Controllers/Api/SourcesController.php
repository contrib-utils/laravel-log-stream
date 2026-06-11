<?php

namespace LogScope\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use LogScope\Sources\LogFileRepository;

class SourcesController
{
    public function index(LogFileRepository $repository): JsonResponse
    {
        return response()->json([
            'data' => $repository->sources(),
        ]);
    }
}
