<?php

namespace LogScope\Parsers;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Resolves the LogParser for a given source key from the 'parser' name in
 * config. Additional parsers register their name => class in the map (M4).
 */
class ParserManager
{
    /** @var array<string, class-string<LogParser>> */
    protected array $map = [
        'laravel' => LaravelParser::class,
    ];

    public function __construct(
        protected Container $container,
    ) {}

    public function extend(string $name, string $class): void
    {
        $this->map[$name] = $class;
    }

    public function for(string $sourceKey): LogParser
    {
        $name = config("logscope.sources.{$sourceKey}.parser", 'generic');

        if (! isset($this->map[$name])) {
            throw new RuntimeException("No LogScope parser registered for '{$name}' (source '{$sourceKey}').");
        }

        return $this->container->make($this->map[$name]);
    }

    public function has(string $sourceKey): bool
    {
        $name = config("logscope.sources.{$sourceKey}.parser", 'generic');

        return isset($this->map[$name]);
    }
}
