<?php

namespace LogScope\Parsers;

/**
 * The file-level context a parser needs to stamp onto every LogEntry it emits.
 */
final readonly class ParseContext
{
    public function __construct(
        public string $sourceKey,
        public string $fileId,
        public string $path,
    ) {}
}
