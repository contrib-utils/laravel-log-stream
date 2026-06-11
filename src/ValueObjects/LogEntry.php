<?php

namespace LogScope\ValueObjects;

use Carbon\CarbonInterface;

final readonly class LogEntry
{
    public function __construct(
        public string $id,             // base64url(fileId@offset) — stable, deep-linkable
        public string $sourceKey,
        public string $file,           // absolute path
        public string $fileId,
        public ?string $level,         // normalized canonical level, or 'unknown'
        public ?string $rawLevel,      // the token as written in the log
        public string $message,
        public ?array $context,        // decoded JSON context, if any
        public ?string $channel,       // e.g. 'production', 'local'
        public ?CarbonInterface $loggedAt,
        public int $offset,            // byte offset of the entry's first line
        public ?string $executionId,   // links to an ExecutionContext (populated in M6)
        public ?string $stack = null,  // multi-line stack trace text, if any
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->sourceKey,
            'file_id' => $this->fileId,
            'level' => $this->level,
            'raw_level' => $this->rawLevel,
            'message' => $this->message,
            'context' => $this->context,
            'channel' => $this->channel,
            'logged_at' => $this->loggedAt?->toIso8601String(),
            'offset' => $this->offset,
            'execution_id' => $this->executionId,
            'stack' => $this->stack,
        ];
    }
}
