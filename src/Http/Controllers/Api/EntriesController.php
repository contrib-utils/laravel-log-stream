<?php

namespace LogScope\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogScope\Parsers\ParserManager;
use LogScope\Sources\LogFileRepository;
use LogScope\Sources\LogReader;
use LogScope\Support\Ids;

class EntriesController
{
    public function __construct(
        protected LogFileRepository $repository,
        protected ParserManager $parsers,
        protected LogReader $reader,
    ) {}

    /**
     * GET /api/files/{fileId}/entries
     */
    public function index(Request $request, string $fileId): JsonResponse
    {
        $file = $this->repository->resolveFileId($fileId);
        abort_if($file === null, 404, 'Log file not found.');

        $parser = $this->parsers->for($file->sourceKey);

        $page = $this->reader->page(
            file: $file,
            parser: $parser,
            cursor: $request->query('cursor'),
            limit: (int) $request->query('per_page', 50),
            direction: $request->query('direction', 'backward') === 'forward' ? 'forward' : 'backward',
            levels: $this->levels($request),
            q: $this->query($request),
        );

        return response()->json($page->toArray());
    }

    /**
     * GET /api/entries/{entryId}
     */
    public function show(string $entryId): JsonResponse
    {
        $decoded = Ids::decodeEntryId($entryId);
        abort_if($decoded === null, 404, 'Entry not found.');

        [$fileId, $offset] = $decoded;

        $file = $this->repository->resolveFileId($fileId);
        abort_if($file === null, 404, 'Log file not found.');

        $entry = $this->reader->entryAt($file, $this->parsers->for($file->sourceKey), $offset);
        abort_if($entry === null, 404, 'Entry not found.');

        return response()->json(['data' => $entry->toArray()]);
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

    protected function query(Request $request): ?string
    {
        $q = $request->query('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }
}
