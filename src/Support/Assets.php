<?php

namespace LogScope\Support;

/**
 * Resolves the compiled Vue SPA assets from the Vite manifest.
 *
 * Assets are built into the package's resources/dist and published to
 * public/vendor/logscope. The build sets base '/vendor/logscope/', so the
 * manifest file paths become web URLs once prefixed with a leading slash.
 */
class Assets
{
    public const BASE = '/vendor/logscope';

    /**
     * @return array{js: string[], css: string[]}
     */
    public static function entry(string $entry = 'resources/js/main.js'): array
    {
        $manifest = self::manifest();

        if ($manifest === null || ! isset($manifest[$entry])) {
            return ['js' => [], 'css' => []];
        }

        $chunk = $manifest[$entry];

        $js = [self::url($chunk['file'])];
        $css = array_map(self::url(...), $chunk['css'] ?? []);

        return ['js' => $js, 'css' => $css];
    }

    public static function published(): bool
    {
        return self::manifest() !== null;
    }

    protected static function url(string $path): string
    {
        return self::BASE.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function manifest(): ?array
    {
        foreach (['/.vite/manifest.json', '/manifest.json'] as $candidate) {
            $path = public_path('vendor/logscope'.$candidate);

            if (is_file($path)) {
                return json_decode((string) file_get_contents($path), true);
            }
        }

        return null;
    }
}
