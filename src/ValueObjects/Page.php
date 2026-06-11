<?php

namespace LogScope\ValueObjects;

/**
 * A cursor-paginated slice of results.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param  array<int, T>  $data
     */
    public function __construct(
        public array $data,
        public ?string $nextCursor = null,
        public ?string $prevCursor = null,
    ) {}

    /**
     * @param  callable(mixed): array  $map  maps each item to its array form
     */
    public function toArray(?callable $map = null): array
    {
        $map ??= fn ($item) => method_exists($item, 'toArray') ? $item->toArray() : $item;

        return [
            'data' => array_map($map, $this->data),
            'meta' => [
                'next_cursor' => $this->nextCursor,
                'prev_cursor' => $this->prevCursor,
            ],
        ];
    }
}
