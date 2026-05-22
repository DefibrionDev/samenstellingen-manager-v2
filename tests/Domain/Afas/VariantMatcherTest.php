<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AmbiguousMatchException;
use Defibrion\Samenstellingen\Domain\Afas\VariantMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VariantMatcherTest extends TestCase
{
    #[Test]
    public function returnsUniqueMatchingSku(): void
    {
        $matcher = new VariantMatcher();
        $expected = ['50013', '50015', '50016', '70112', '81111'];
        $candidates = [
            new AfasSamenstelling('52112', 'AED pakket NL', '52112', $expected),
            new AfasSamenstelling('52124', 'Pack DAE FR', '52112', ['50001', '50015', '50016', '70112', '81151']),
            new AfasSamenstelling('52112-60110', 'NL + Rugzak', '52112', [...$expected, '60110']),
        ];

        self::assertSame('52112', $matcher->findMatch(42, $expected, $candidates));
    }

    #[Test]
    public function returnsNullWhenNoMatch(): void
    {
        $matcher = new VariantMatcher();
        $candidates = [
            new AfasSamenstelling('52124', 'X', null, ['50001', '50015']),
        ];

        self::assertNull($matcher->findMatch(42, ['50013', '50015'], $candidates));
    }

    #[Test]
    public function throwsOnAmbiguousMatch(): void
    {
        $matcher = new VariantMatcher();
        $expected = ['50013', '50015'];
        $candidates = [
            new AfasSamenstelling('A', 'A naam', null, $expected),
            new AfasSamenstelling('B', 'B naam', null, $expected),
        ];

        $this->expectException(AmbiguousMatchException::class);
        $this->expectExceptionMessage('A, B');
        $this->expectExceptionMessage('variant #42');

        $matcher->findMatch(42, $expected, $candidates);
    }

    #[Test]
    public function ignoresOrderInBom(): void
    {
        $matcher = new VariantMatcher();
        $candidates = [
            new AfasSamenstelling('52112', 'NL', null, ['70112', '50016', '50015', '50013', '81111']),
        ];

        self::assertSame(
            '52112',
            $matcher->findMatch(1, ['50013', '50015', '50016', '70112', '81111'], $candidates),
        );
    }
}
