<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AfasPrijslijstTest extends TestCase
{
    #[Test]
    public function constructsAndTrims(): void
    {
        $p = new AfasPrijslijst('  010  ', '  Farys  ');
        self::assertSame('010', $p->id);
        self::assertSame('Farys', $p->omschrijving);
    }

    #[Test]
    public function emptyIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AfasPrijslijst('', 'Farys');
    }

    #[Test]
    public function emptyOmschrijvingThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AfasPrijslijst('010', '');
    }
}
