<?php

namespace LogScope\Parsers;

use LogScope\ValueObjects\LogEntry;

interface LogParser
{
    /**
     * Whether this parser handles the given source key.
     */
    public function supports(string $sourceKey): bool;

    /**
     * Does this line begin a new log entry? Lines for which this returns false
     * are treated as continuations (e.g. stack-trace lines) of the entry above.
     * The LogReader uses this to find entry boundaries without knowing the
     * concrete log format.
     */
    public function isEntryStart(string $line): bool;

    /**
     * Turn an ordered (forward) sequence of offset-tagged lines into entries.
     *
     * @param  iterable<array{offset: int, text: string}>  $lines
     * @return iterable<LogEntry>
     */
    public function parse(iterable $lines, ParseContext $context): iterable;
}
