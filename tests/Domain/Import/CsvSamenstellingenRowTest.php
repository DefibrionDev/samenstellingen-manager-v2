<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Import;

use Defibrion\Samenstellingen\Domain\Import\CsvSamenstellingenRow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsvSamenstellingenRowTest extends TestCase
{
    #[Test]
    public function detectsBaseOnlySamenstelling(): void
    {
        $row = new CsvSamenstellingenRow('52124', 'Pack DAE FR', '50001', 'AED FR');

        self::assertTrue($row->isBaseOnly());
        self::assertNull($row->extractAccessoireItemcode());
    }

    #[Test]
    public function detectsVariantWithAccessoire(): void
    {
        $row = new CsvSamenstellingenRow('52124-60110', 'Pack DAE FR + Sac à dos', '50001', 'AED FR');

        self::assertFalse($row->isBaseOnly());
        self::assertSame('60110', $row->extractAccessoireItemcode());
    }
}
