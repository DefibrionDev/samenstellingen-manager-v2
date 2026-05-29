<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingPlan;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VariantFixMissingPlanTest extends TestCase
{
    #[Test]
    public function storesAllFieldsOnConstruction(): void
    {
        $plan = new VariantFixMissingPlan(
            afasItemcode: '11111-60212',
            canonicalName: 'AED Pakket: Heartsine Samaritan PAD 350P NL met ARKY buitenkast (onverwarmd)',
            bomItemcodes: ['10111', '10211', '70112', '81111', '60212'],
            familyHeadItemcode: '10013',
            baseAfasItemcode: '11111',
            referenceVariantItemcode: '11111-60112',
        );

        self::assertSame('11111-60212', $plan->afasItemcode);
        self::assertSame('11111', $plan->baseAfasItemcode);
        self::assertSame('10013', $plan->familyHeadItemcode);
        self::assertSame('11111-60112', $plan->referenceVariantItemcode);
        self::assertSame(['10111', '10211', '70112', '81111', '60212'], $plan->bomItemcodes);
    }

    #[Test]
    public function rejectsEmptyItemcode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VariantFixMissingPlan('', 'naam', ['10111'], '10013', '11111', '11111-60112');
    }

    #[Test]
    public function rejectsEmptyCanonicalName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VariantFixMissingPlan('11111-60212', '   ', ['10111'], '10013', '11111', '11111-60112');
    }

    #[Test]
    public function rejectsEmptyBom(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VariantFixMissingPlan('11111-60212', 'naam', [], '10013', '11111', '11111-60112');
    }
}
