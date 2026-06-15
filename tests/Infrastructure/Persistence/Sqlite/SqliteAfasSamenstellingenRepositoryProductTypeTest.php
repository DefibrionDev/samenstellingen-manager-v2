<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\ItemProductTypes;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqliteAfasSamenstellingenRepositoryProductTypeTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    #[Test]
    public function storesAndReadsProductTypesPerSamenstelling(): void
    {
        $repo = new SqliteAfasSamenstellingenRepository(TestDatabase::pdo());
        $repo->replaceSnapshot([
            new AfasSamenstelling('11111-60110', 'AED Pakket: ... met Rugtas', '11111', []),
            new AfasSamenstelling('11111', 'AED Pakket: ...', '11111', []),
        ]);

        $repo->updateProductTypes([
            new ItemProductTypes('11111-60110', 'AED pakket', '350P'),
            // Itemcode dat niet als samenstelling bestaat → moet overgeslagen worden.
            new ItemProductTypes('99999-onbekend', 'X', 'Y'),
        ]);

        $variant = $repo->findByItemcode('11111-60110');
        self::assertNotNull($variant);
        self::assertSame('AED pakket', $variant->productType01);
        self::assertSame('350P', $variant->productType02);

        // Niet-bestaand itemcode is niet aangemaakt.
        self::assertNull($repo->findByItemcode('99999-onbekend'));

        // Ongemoeide samenstelling houdt lege producttypes.
        $base = $repo->findByItemcode('11111');
        self::assertNotNull($base);
        self::assertNull($base->productType01);
        self::assertNull($base->productType02);
    }

    #[Test]
    public function findAllExposesProductTypes(): void
    {
        $repo = new SqliteAfasSamenstellingenRepository(TestDatabase::pdo());
        $repo->replaceSnapshot([
            new AfasSamenstelling('11111-60110', 'AED Pakket: ... met Rugtas', '11111', []),
        ]);
        $repo->updateProductTypes([
            new ItemProductTypes('11111-60110', 'AED pakket', '350P'),
        ]);

        $all = $repo->findAll();
        self::assertCount(1, $all);
        self::assertSame('AED pakket', $all[0]->productType01);
        self::assertSame('350P', $all[0]->productType02);
    }
}
