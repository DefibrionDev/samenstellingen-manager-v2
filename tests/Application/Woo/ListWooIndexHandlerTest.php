<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Woo;

use Defibrion\Samenstellingen\Application\Woo\ListWooIndex;
use Defibrion\Samenstellingen\Application\Woo\ListWooIndexHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooCommerceStoreRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooProductRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListWooIndexHandlerTest extends TestCase
{
    #[Test]
    public function rowsContainOneEntryPerManagedItemcodeAcrossAllStores(): void
    {
        $bag = $this->scaffold();
        // Managed: base 11111 + variant 11111-60110
        $bag['products']->replaceForStore($bag['storeNlId'], [
            new WooProduct(1, 'simple', 'sku-1', 'Base NL shop', 'publish', 'https://nl/1', '11111'),
            new WooProductVariation(2, 5, 'sku-2', 'Backpack', 'publish', 'https://nl/2', '11111-60110'),
        ]);
        $bag['products']->replaceForStore($bag['storeFrId'], [
            new WooProduct(10, 'simple', null, 'Base FR shop', 'publish', null, '11111'),
        ]);

        $result = ($bag['handler'])(new ListWooIndex(null, false, false));

        self::assertCount(2, $result->rows);
        $codes = array_map(static fn ($r) => $r->afasItemcode, $result->rows);
        sort($codes, SORT_STRING);
        self::assertSame(['11111', '11111-60110'], $codes);

        $rowBase = $result->rows[0]->afasItemcode === '11111' ? $result->rows[0] : $result->rows[1];
        $rowVariant = $result->rows[0]->afasItemcode === '11111-60110' ? $result->rows[0] : $result->rows[1];

        // 11111: in beide stores
        self::assertNotNull($rowBase->cellsByStore[$bag['storeNlId']]);
        self::assertNotNull($rowBase->cellsByStore[$bag['storeFrId']]);
        self::assertSame('publish', $rowBase->cellsByStore[$bag['storeNlId']]->status);
        // 11111-60110: alleen in NL
        self::assertNotNull($rowVariant->cellsByStore[$bag['storeNlId']]);
        self::assertNull($rowVariant->cellsByStore[$bag['storeFrId']]);
    }

    #[Test]
    public function missingOnlyFiltersToRowsWhereAtLeastOneStoreCellIsNull(): void
    {
        $bag = $this->scaffold();
        $bag['products']->replaceForStore($bag['storeNlId'], [
            new WooProduct(1, 'simple', null, 'Base NL', 'publish', null, '11111'),
            new WooProduct(2, 'simple', null, 'Variant NL', 'publish', null, '11111-60110'),
        ]);
        $bag['products']->replaceForStore($bag['storeFrId'], [
            new WooProduct(10, 'simple', null, 'Base FR', 'publish', null, '11111'),
        ]);

        $result = ($bag['handler'])(new ListWooIndex(null, true, false));

        // Alleen 11111-60110 mist in FR
        self::assertCount(1, $result->rows);
        self::assertSame('11111-60110', $result->rows[0]->afasItemcode);
    }

    #[Test]
    public function orphansAreWcProductsWithoutManagedItemcodeMatch(): void
    {
        $bag = $this->scaffold();
        $bag['products']->replaceForStore($bag['storeNlId'], [
            new WooProduct(1, 'simple', null, 'Bekende base', 'publish', null, '11111'),
            new WooProduct(2, 'simple', null, 'Niet-managed AFAS-link', 'publish', null, '99999'),
            new WooProduct(3, 'simple', null, 'Geen AFAS-meta', 'publish', null, null),
        ]);

        $result = ($bag['handler'])(new ListWooIndex(null, false, false));

        self::assertCount(2, $result->orphans);
        $orphanWith99999 = null;
        $orphanWithNullMeta = null;
        foreach ($result->orphans as $orphan) {
            if ($orphan->afasItemcode === '99999') {
                $orphanWith99999 = $orphan;
            }
            if ($orphan->afasItemcode === null) {
                $orphanWithNullMeta = $orphan;
            }
        }
        self::assertNotNull($orphanWith99999);
        self::assertNotNull($orphanWithNullMeta);
        self::assertSame(2, $orphanWith99999->wcProductId);
        self::assertSame(3, $orphanWithNullMeta->wcProductId);
        self::assertSame('defibrion.nl', $orphanWith99999->storeName);
    }

    #[Test]
    public function storeFilterLimitsRowsAndOrphansToSelectedStore(): void
    {
        $bag = $this->scaffold();
        $bag['products']->replaceForStore($bag['storeNlId'], [
            new WooProduct(1, 'simple', null, 'NL match', 'publish', null, '11111'),
            new WooProduct(2, 'simple', null, 'NL orphan', 'publish', null, null),
        ]);
        $bag['products']->replaceForStore($bag['storeFrId'], [
            new WooProduct(10, 'simple', null, 'FR match', 'publish', null, '11111'),
            new WooProduct(11, 'simple', null, 'FR orphan', 'publish', null, null),
        ]);

        $result = ($bag['handler'])(new ListWooIndex('defibrion.fr', false, false));

        // Rows: alleen kolommen voor FR.
        self::assertCount(2, $result->rows);
        foreach ($result->rows as $row) {
            self::assertArrayHasKey($bag['storeFrId'], $row->cellsByStore);
            self::assertArrayNotHasKey($bag['storeNlId'], $row->cellsByStore);
        }
        // Orphans: alleen FR-orphan.
        self::assertCount(1, $result->orphans);
        self::assertSame(11, $result->orphans[0]->wcProductId);
    }

    #[Test]
    public function emptyManagedSetYieldsEmptyRowsAndAllProductsAsOrphans(): void
    {
        $bag = $this->scaffold(seedManaged: false);
        $bag['products']->replaceForStore($bag['storeNlId'], [
            new WooProduct(1, 'simple', null, 'X', 'publish', null, '11111'),
        ]);

        $result = ($bag['handler'])(new ListWooIndex(null, false, false));

        self::assertSame([], $result->rows);
        self::assertCount(1, $result->orphans);
    }

    /**
     * @param bool $seedManaged Seed 2 stores + 1 group with base 11111 + accessoire 60110 (managed-set = {11111, 11111-60110}).
     *
     * @return array{
     *     handler: ListWooIndexHandler,
     *     products: InMemoryWooProductRepository,
     *     storeNlId: int,
     *     storeFrId: int,
     * }
     */
    private function scaffold(bool $seedManaged = true): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $stores = new InMemoryWooCommerceStoreRepository();
        $products = new InMemoryWooProductRepository();

        $nl = $stores->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        $fr = $stores->save(new WooCommerceStore(null, 'defibrion.fr', 'https://defibrion.fr', 'ck', 'cs'));
        self::assertNotNull($nl->id);
        self::assertNotNull($fr->id);

        if ($seedManaged) {
            $groups->save(new Group('Heartsine 350P', '10013'));
            $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
            self::assertNotNull($base->id);
            $accessoires->save(new Accessoire('60110', 'Rugzak'));
            $links->link('10013', '60110');
            $variants->regenerateForGroup('10013');
            foreach ($variants->findAllForGroup('10013') as $variant) {
                self::assertNotNull($variant->id);
                if ($variant->accessoireItemcode === '60110') {
                    $variants->markMatched($variant->id, '11111-60110');
                }
            }
        }

        $handler = new ListWooIndexHandler($bases, $variants, $groups, $stores, $products);

        return [
            'handler' => $handler,
            'products' => $products,
            'storeNlId' => $nl->id,
            'storeFrId' => $fr->id,
        ];
    }
}
