<?php

namespace LogScope\Support;

/**
 * Opaque, URL-safe identifiers. A file id is the base64url of its absolute
 * path; an entry id additionally encodes the byte offset of the entry. Both
 * are always re-validated through the path-traversal guard before any read —
 * decoding here grants no access on its own.
 */
class Ids
{
    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    public static function fileId(string $absolutePath): string
    {
        return self::encode($absolutePath);
    }

    public static function entryId(string $fileId, int $offset): string
    {
        return self::encode($fileId.'@'.$offset);
    }

    /**
     * @return array{0: string, 1: int}|null [fileId, offset] or null when malformed
     */
    public static function decodeEntryId(string $entryId): ?array
    {
        $decoded = self::decode($entryId);

        if ($decoded === '' || ! str_contains($decoded, '@')) {
            return null;
        }

        $at = strrpos($decoded, '@');
        $fileId = substr($decoded, 0, $at);
        $offset = substr($decoded, $at + 1);

        if ($fileId === '' || ! ctype_digit($offset)) {
            return null;
        }

        return [$fileId, (int) $offset];
    }

    /**
     * A cross-file search cursor pins the current file (by its id) and the
     * within-file byte cursor the reader handed back, so the next page resumes
     * exactly where the last one stopped — even across file boundaries.
     */
    public static function searchCursor(string $fileId, ?string $inner): string
    {
        return self::encode(json_encode(['f' => $fileId, 'c' => $inner], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: string, 1: ?string}|null [fileId, innerCursor] or null when malformed
     */
    public static function decodeSearchCursor(string $cursor): ?array
    {
        $decoded = json_decode(self::decode($cursor), true);

        if (! is_array($decoded) || ! isset($decoded['f']) || ! is_string($decoded['f'])) {
            return null;
        }

        $inner = $decoded['c'] ?? null;

        return [$decoded['f'], is_string($inner) ? $inner : null];
    }
}
