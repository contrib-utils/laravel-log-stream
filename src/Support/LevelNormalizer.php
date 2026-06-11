<?php

namespace LogScope\Support;

/**
 * Maps raw severity tokens found in logs to the canonical level set defined
 * in config. Unrecognised tokens normalise to 'unknown' (never dropped), so
 * they remain searchable and filterable per the spec.
 */
class LevelNormalizer
{
    /** @var array<string, true> canonical level keys */
    protected array $levels;

    /** @var array<string, string> lowercased alias => canonical key */
    protected array $aliases;

    public function __construct(array $levels, array $aliases)
    {
        $this->levels = array_change_key_case(
            array_fill_keys(array_keys($levels), true),
            CASE_LOWER,
        );

        $this->aliases = [];
        foreach ($aliases as $from => $to) {
            $this->aliases[strtolower((string) $from)] = strtolower((string) $to);
        }
    }

    public function normalize(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'unknown';
        }

        $key = strtolower(trim($raw));

        if (isset($this->levels[$key])) {
            return $key;
        }

        if (isset($this->aliases[$key]) && isset($this->levels[$this->aliases[$key]])) {
            return $this->aliases[$key];
        }

        return 'unknown';
    }
}
