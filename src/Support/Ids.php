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
}
