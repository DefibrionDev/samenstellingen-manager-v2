<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Money;

use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class EuroParserTest extends TestCase
{
    #[Test]
    #[TestWith(['79', 7900])]
    #[TestWith(['79.50', 7950])]
    #[TestWith(['79,50', 7950])]
    #[TestWith(['0', 0])]
    #[TestWith(['0.00', 0])]
    #[TestWith(['1234.99', 123499])]
    #[TestWith(['1234,99', 123499])]
    #[TestWith(['  79  ', 7900])]
    #[TestWith(['79.5', 7950])]
    #[TestWith(['79.05', 7905])]
    #[TestWith(['€ 79,50', 7950])]
    #[TestWith(['€79', 7900])]
    public function parsesEuroToCents(string $input, int $expectedCents): void
    {
        self::assertSame($expectedCents, EuroParser::toCents($input));
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['abc'])]
    #[TestWith(['79.999'])]
    #[TestWith(['79,5,5'])]
    #[TestWith(['1.234.567'])]
    #[TestWith(['-1'])]
    #[TestWith(['-1.50'])]
    public function throwsForInvalidInput(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        EuroParser::toCents($input);
    }

    #[Test]
    public function formatsCentsAsEuroDisplayString(): void
    {
        self::assertSame('€ 79,50', EuroParser::formatCents(7950));
        self::assertSame('€ 0,00', EuroParser::formatCents(0));
        self::assertSame('€ 1.234,99', EuroParser::formatCents(123499));
    }
}
