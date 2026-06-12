<?php

namespace LogScope\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogScope\Sources\LogSearcher;

/**
 * GET /api/search — cross-file search within a source (M3).
 */
class SearchController
{
    public function __construct(
        protected LogSearcher $searcher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $source = (string) $request->query('source', 'laravel');
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([
                'data' => [],
                'meta' => ['next_cursor' => null, 'prev_cursor' => null, 'source' => $source],
            ]);
        }

        $page = $this->searcher->search(
            sourceKey: $source,
            q: $q,
            cursor: $request->query('cursor'),
            limit: (int) $request->query('per_page', 50),
            levels: $this->levels($request),
        );

        // Rows are already array form (entry + file_name), so map identity.
        $payload = $page->toArray(fn (array $row) => $row);
        $payload['meta']['source'] = $source;

        return response()->json($payload);
    }

    /**
     * @return string[]|null
     */
    protected function levels(Request $request): ?array
    {
        $raw = $request->query('level');

        if ($raw === null || $raw === '') {
            return null;
        }

        $levels = array_filter(array_map(
            fn ($l) => strtolower(trim($l)),
            explode(',', (string) $raw),
        ));

        return $levels === [] ? null : array_values($levels);
    }
}
