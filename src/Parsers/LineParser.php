<?php

namespace LogScope\Parsers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use LogScope\Support\LevelNormalizer;
use LogScope\ValueObjects\LogEntry;
use Throwable;

/**
 * Base for the line-oriented (non-Laravel) parsers: nginx, apache, redis,
 * postgres, supervisor, and the generic fallback.
 *
 * These formats put one record per line, so an entry starts at any non-blank,
 * non-indented line; indented or blank lines fold into the entry above as
 * continuations. Concrete parsers only implement {@see buildEntry()}.
 */
abstract class LineParser extends AbstractParser
{
    public function __construct(
        protected LevelNormalizer $levels,
    ) {}

    public function isEntryStart(string $line): bool
    {
        return $line !== '' && ! ctype_space($line[0]);
    }

    /**
     * Assemble a LogEntry, normalising the level and folding continuation
     * lines into a stack/extra block.
     *
     * @param  string[]  $continuations
     */
    protected function makeEntry(
        int $offset,
        ParseContext $context,
        ?string $rawLevel,
        string $message,
        ?CarbonInterface $loggedAt = null,
        ?string $channel = null,
        ?array $contextData = null,
        array $continuations = [],
    ): LogEntry {
        return new LogEntry(
            id: $this->entryId($context, $offset),
            sourceKey: $context->sourceKey,
            file: $context->path,
            fileId: $context->fileId,
            level: $this->levels->normalize($rawLevel),
            rawLevel: $rawLevel,
            message: $message,
            context: $contextData,
            channel: $channel,
            loggedAt: $loggedAt,
            offset: $offset,
            executionId: null,
            stack: $continuations === [] ? null : implode("\n", $continuations),
        );
    }

    protected function carbon(?string $ts, ?string $format = null): ?CarbonInterface
    {
        if ($ts === null || trim($ts) === '') {
            return null;
        }

        try {
            return $format !== null
                ? (Carbon::createFromFormat($format, $ts) ?: null)
                : Carbon::parse($ts);
        } catch (Throwable) {
            return null;
        }
    }
}
