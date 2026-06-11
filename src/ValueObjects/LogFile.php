<?php

namespace LogScope\ValueObjects;

final readonly class LogFile
{
    public function __construct(
        public string $id,        // base64url(absolute path)
        public string $sourceKey,
        public string $path,      // absolute path
        public string $name,      // basename
        public int $size,         // bytes
        public int $mtime,        // unix timestamp
        public bool $readable,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->sourceKey,
            'name' => $this->name,
            'path' => $this->path,
            'size' => $this->size,
            'mtime' => $this->mtime,
            'readable' => $this->readable,
        ];
    }
}
