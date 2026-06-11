<?php

namespace LogScope\Parsers;

use LogScope\Support\Ids;
use LogScope\ValueObjects\LogEntry;

/**
 * Shared multi-line grouping: lines are accumulated under the most recent
 * entry-start line, so stack traces and other continuations belong to the
 * entry above them. Concrete parsers implement isEntryStart() and buildEntry().
 */
abstract class AbstractParser implements LogParser
{
    /**
     * @param  iterable<array{offset: int, text: string}>  $lines
     * @return iterable<LogEntry>
     */
    public function parse(iterable $lines, ParseContext $context): iterable
    {
        $offset = null;
        $buffer = [];

        foreach ($lines as $line) {
            if ($this->isEntryStart($line['text'])) {
                if ($buffer !== []) {
                    if ($entry = $this->emit($offset, $buffer, $context)) {
                        yield $entry;
                    }
                }
                $offset = $line['offset'];
                $buffer = [$line['text']];
            } elseif ($buffer === []) {
                // Leading continuation lines with no preceding start (e.g. a
                // window that begins mid-entry). Anchor an entry here anyway so
                // nothing is dropped.
                $offset = $line['offset'];
                $buffer = [$line['text']];
            } else {
                $buffer[] = $line['text'];
            }
        }

        if ($buffer !== []) {
            if ($entry = $this->emit($offset, $buffer, $context)) {
                yield $entry;
            }
        }
    }

    /**
     * @param  string[]  $textLines
     */
    protected function emit(int $offset, array $textLines, ParseContext $context): ?LogEntry
    {
        return $this->buildEntry($offset, $textLines, $context);
    }

    protected function entryId(ParseContext $context, int $offset): string
    {
        return Ids::entryId($context->fileId, $offset);
    }

    /**
     * @param  string[]  $textLines  the entry's lines (first line + continuations), no trailing newlines
     */
    abstract protected function buildEntry(int $offset, array $textLines, ParseContext $context): ?LogEntry;
}
