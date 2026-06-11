<?php

namespace LogScope\Tests\Unit;

use LogScope\Support\LevelNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LevelNormalizerTest extends TestCase
{
    protected function normalizer(): LevelNormalizer
    {
        return new LevelNormalizer(
            levels: [
                'debug' => [], 'info' => [], 'warning' => [],
                'error' => [], 'critical' => [], 'unknown' => [],
            ],
            aliases: ['FATAL' => 'critical', 'warn' => 'warning', 'crit' => 'critical'],
        );
    }

    #[Test]
    public function canonical_levels_pass_through_case_insensitively(): void
    {
        $n = $this->normalizer();

        $this->assertSame('error', $n->normalize('error'));
        $this->assertSame('error', $n->normalize('ERROR'));
        $this->assertSame('warning', $n->normalize('Warning'));
    }

    #[Test]
    public function aliases_map_to_canonical_levels(): void
    {
        $n = $this->normalizer();

        $this->assertSame('critical', $n->normalize('FATAL'));
        $this->assertSame('critical', $n->normalize('fatal'));
        $this->assertSame('warning', $n->normalize('warn'));
        $this->assertSame('critical', $n->normalize('CRIT'));
    }

    #[Test]
    public function unrecognised_or_empty_tokens_become_unknown(): void
    {
        $n = $this->normalizer();

        $this->assertSame('unknown', $n->normalize('bogus'));
        $this->assertSame('unknown', $n->normalize(''));
        $this->assertSame('unknown', $n->normalize('   '));
        $this->assertSame('unknown', $n->normalize(null));
    }
}
