<?php

namespace LogScope\Sources;

use LogScope\Parsers\ParserManager;
use LogScope\Support\Ids;
use LogScope\ValueObjects\LogFile;
use LogScope\ValueObjects\Page;

/**
 * Cross-file search (M3). Walks a source's files newest-first (by mtime) and
 * delegates each file to the same byte-offset {@see LogReader}, so a query
 * spans every rotated log without ever loading a whole file into memory.
 *
 * Pagination uses a composite cursor (see {@see Ids::searchCursor()}) that
 * pins the file currently being read plus the reader's within-file cursor, so
 * the next page resumes exactly where this one stopped — including the seam
 * between two files.
 *
 * Each returned row is the entry's array form enriched with the human file
 * name/id it came from, since results interleave files.
 */
class LogSearcher
{
    public function __construct(
        protected LogFileRepository $repository,
        protected ParserManager $parsers,
        protected LogReader $reader,
    ) {}

    /**
     * @param  string[]|null  $levels
     */
    public function search(
        string $sourceKey,
        string $q,
        ?string $cursor,
        int $limit,
        ?array $levels = null,
    ): Page {
        $limit = max(1, min($limit, 200));

        $files = $this->repository->filesFor($sourceKey);
        if ($files === []) {
            return new Page([], null, null);
        }

        $parser = $this->parsers->for($sourceKey);

        [$startIndex, $inner] = $this->resolveCursor($cursor, $files);

        $rows = [];
        $next = null;

        for ($i = $startIndex; $i < count($files); $i++) {
            $file = $files[$i];
            $remaining = $limit - count($rows);

            $page = $this->reader->page(
                file: $file,
                parser: $parser,
                cursor: $i === $startIndex ? $inner : null,
                limit: $remaining,
                direction: 'backward',
                levels: $levels,
                q: $q,
            );

            foreach ($page->data as $entry) {
                $row = $entry->toArray();
                $row['file_name'] = $file->name;
                $rows[] = $row;
            }

            if (count($rows) >= $limit) {
                // Page filled. Either more remains in this file (inner cursor),
                // or we hand off to the head of the next file.
                $next = $page->nextCursor !== null
                    ? Ids::searchCursor($file->id, $page->nextCursor)
                    : (isset($files[$i + 1]) ? Ids::searchCursor($files[$i + 1]->id, null) : null);
                break;
            }
        }

        return new Page($rows, $next, null);
    }

    /**
     * @param  LogFile[]  $files
     * @return array{0: int, 1: ?string} [startFileIndex, innerCursor]
     */
    protected function resolveCursor(?string $cursor, array $files): array
    {
        if ($cursor === null || $cursor === '') {
            return [0, null];
        }

        $decoded = Ids::decodeSearchCursor($cursor);
        if ($decoded === null) {
            return [0, null];
        }

        [$fileId, $inner] = $decoded;

        foreach ($files as $index => $file) {
            if ($file->id === $fileId) {
                return [$index, $inner];
            }
        }

        // The pinned file rotated away between requests — start over rather
        // than silently skip newer files.
        return [0, null];
    }
}
