<?php

namespace LogScope\Sources;

use LogScope\Support\Ids;
use LogScope\ValueObjects\LogFile;

/**
 * Discovers log files for the configured sources and safely resolves an opaque
 * file id back to a real, allow-listed path.
 *
 * Security: globs are resolved at request time (files rotate). Any id coming
 * from the client is decoded, realpath-resolved (collapsing `..` and symlinks),
 * and rejected unless it lands inside one of its source's static glob roots and
 * matches one of that source's patterns.
 */
class LogFileRepository
{
    /**
     * Enabled sources as [key => label].
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function sources(): array
    {
        $out = [];

        foreach ((array) config('logscope.sources', []) as $key => $source) {
            if (($source['enabled'] ?? true) === false) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $source['label'] ?? ucfirst($key),
            ];
        }

        return $out;
    }

    /**
     * Files for a source, newest (by mtime) first.
     *
     * @return LogFile[]
     */
    public function filesFor(string $sourceKey): array
    {
        $patterns = $this->patternsFor($sourceKey);

        $paths = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern, GLOB_NOSORT) ?: [] as $path) {
                // Canonicalise so a file's id is stable regardless of how it was
                // reached (symlinked temp dirs, `..`, etc.).
                $real = realpath($path);
                if ($real !== false && is_file($real)) {
                    $paths[$real] = true;
                }
            }
        }

        $files = [];
        foreach (array_keys($paths) as $path) {
            $files[] = $this->makeFile($sourceKey, $path);
        }

        usort($files, fn (LogFile $a, LogFile $b) => $b->mtime <=> $a->mtime);

        return $files;
    }

    /**
     * Resolve a client-supplied file id to a validated LogFile, or null when it
     * is malformed, missing, or escapes its allow-listed roots.
     */
    public function resolveFileId(string $fileId): ?LogFile
    {
        $path = Ids::decode($fileId);

        if ($path === '' || ! is_file($path)) {
            return null;
        }

        $real = realpath($path);
        if ($real === false) {
            return null;
        }

        foreach (array_keys((array) config('logscope.sources', [])) as $sourceKey) {
            if (($this->sourceConfig($sourceKey)['enabled'] ?? true) === false) {
                continue;
            }

            if ($this->pathBelongsToSource($real, $sourceKey)) {
                return $this->makeFile($sourceKey, $real);
            }
        }

        return null;
    }

    protected function pathBelongsToSource(string $real, string $sourceKey): bool
    {
        foreach ($this->patternsFor($sourceKey) as $pattern) {
            $root = $this->staticRoot($pattern);
            $realRoot = $root !== null ? realpath($root) : false;

            if ($realRoot === false) {
                continue;
            }

            $within = $real === $realRoot
                || str_starts_with($real, $realRoot.DIRECTORY_SEPARATOR);

            // Containment is checked against the realpath'd root; the filename
            // glob is matched by basename so a symlinked root dir (e.g. macOS
            // /var → /private/var) doesn't defeat the pattern match.
            if ($within && fnmatch(basename($pattern), basename($real))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    protected function patternsFor(string $sourceKey): array
    {
        return array_values(array_filter(
            (array) ($this->sourceConfig($sourceKey)['paths'] ?? []),
            'is_string',
        ));
    }

    protected function sourceConfig(string $sourceKey): array
    {
        return (array) config("logscope.sources.{$sourceKey}", []);
    }

    /**
     * The longest leading directory of a glob pattern that contains no wildcard.
     */
    protected function staticRoot(string $pattern): ?string
    {
        $segments = explode('/', $pattern);
        $root = [];

        foreach ($segments as $segment) {
            if (preg_match('/[*?\[\]{}]/', $segment) === 1) {
                break;
            }
            $root[] = $segment;
        }

        $path = implode('/', $root);

        // For an absolute pattern, the first segment is '' → restore leading slash.
        if (str_starts_with($pattern, '/') && ! str_starts_with($path, '/')) {
            $path = '/'.ltrim($path, '/');
        }

        $dir = is_dir($path) ? $path : dirname($path);

        return $dir !== '' ? $dir : null;
    }

    protected function makeFile(string $sourceKey, string $path): LogFile
    {
        $readable = is_readable($path);

        return new LogFile(
            id: Ids::fileId($path),
            sourceKey: $sourceKey,
            path: $path,
            name: basename($path),
            size: $readable ? (int) (@filesize($path) ?: 0) : 0,
            mtime: (int) (@filemtime($path) ?: 0),
            readable: $readable,
        );
    }
}
