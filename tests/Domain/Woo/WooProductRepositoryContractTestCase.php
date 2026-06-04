<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Woo;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class WooProductRepositoryContractTestCase extends TestCase
{
    /**
     * @return array{stores: WooCommerceStoreRepository, products: WooProductRepository}
     */
    abstract protected function makeRepositories(): array;

    private WooCommerceStoreRepository $stores;
    private WooProductRepository $products;
    private int $storeNlId;
    private int $storeFrId;

    protected function setUp(): void
    {
        $repos = $this->makeRepositories();
        $this->stores = $repos['stores'];
        $this->products = $repos['products'];

        $nl = $this->stores->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck1', 'cs1'));
        $fr = $this->stores->save(new WooCommerceStore(null, 'defibrion.fr', 'https://defibrion.fr', 'ck2', 'cs2'));
        self::assertNotNull($nl->id);
        self::assertNotNull($fr->id);
        $this->storeNlId = $nl->id;
        $this->storeFrId = $fr->id;
    }

    #[Test]
    public function replaceForStorePersistsItems(): void
    {
        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(101, 'simple', 'sku-101', 'Mindray semi', 'publish', 'https://defibrion.nl/p/101', '21011'),
            new WooProduct(102, 'variable', null, 'Mindray semi 4G', 'publish', 'https://defibrion.nl/p/102', null),
            new WooProductVariation(201, 102, 'sku-201', 'Mindray semi 4G – Backpack', 'publish', 'https://defibrion.nl/p/102?attr=60110', '21018-60110'),
        ]);

        $items = $this->products->findAllForStore($this->storeNlId);

        self::assertCount(3, $items);
    }

    #[Test]
    public function replaceForStoreClearsPreviousItems(): void
    {
        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(101, 'simple', 'sku-101', 'Eerste', 'publish', null, '21011'),
            new WooProduct(102, 'simple', 'sku-102', 'Tweede', 'publish', null, '21012'),
        ]);

        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(103, 'simple', 'sku-103', 'Vervangen', 'publish', null, '21013'),
        ]);

        $items = $this->products->findAllForStore($this->storeNlId);
        self::assertCount(1, $items);
        self::assertSame(103, $items[0]->wcProductId);
    }

    #[Test]
    public function replaceForStoreLeavesOtherStoresUntouched(): void
    {
        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(101, 'simple', null, 'NL-product', 'publish', null, '21011'),
        ]);
        $this->products->replaceForStore($this->storeFrId, [
            new WooProduct(201, 'simple', null, 'FR-product', 'publish', null, '21011-FR'),
        ]);

        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(102, 'simple', null, 'NL-vervangen', 'publish', null, '21012'),
        ]);

        $nlItems = $this->products->findAllForStore($this->storeNlId);
        $frItems = $this->products->findAllForStore($this->storeFrId);
        self::assertCount(1, $nlItems);
        self::assertSame(102, $nlItems[0]->wcProductId);
        self::assertCount(1, $frItems);
        self::assertSame(201, $frItems[0]->wcProductId);
    }

    #[Test]
    public function findByAfasItemcodeReturnsMatchesAcrossStores(): void
    {
        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(101, 'simple', null, 'NL', 'publish', null, '21011'),
            new WooProduct(102, 'simple', null, 'NL andere', 'publish', null, '21012'),
        ]);
        $this->products->replaceForStore($this->storeFrId, [
            new WooProduct(201, 'simple', null, 'FR', 'publish', null, '21011'),
        ]);

        $matches = $this->products->findByAfasItemcode('21011');

        self::assertCount(2, $matches);
        self::assertSame($this->storeNlId, $matches[0]['store_id']);
        self::assertSame($this->storeFrId, $matches[1]['store_id']);
    }

    #[Test]
    public function findByAfasItemcodeSkipsNullItemcodes(): void
    {
        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(101, 'variable', null, 'NL parent', 'publish', null, null),
        ]);

        self::assertSame([], $this->products->findByAfasItemcode(''));
        self::assertSame([], $this->products->findByAfasItemcode('21011'));
    }

    #[Test]
    public function replaceForStoreWithEmptyListClearsStore(): void
    {
        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(101, 'simple', null, 'X', 'publish', null, '21011'),
        ]);
        $this->products->replaceForStore($this->storeNlId, []);

        self::assertSame([], $this->products->findAllForStore($this->storeNlId));
    }

    #[Test]
    public function variationRetainsParentIdAndType(): void
    {
        $this->products->replaceForStore($this->storeNlId, [
            new WooProduct(102, 'variable', null, 'Parent', 'publish', null, null),
            new WooProductVariation(201, 102, 'sku-201', 'Child', 'publish', null, '21011-60110'),
        ]);

        $items = $this->products->findAllForStore($this->storeNlId);
        $byId = [];
        foreach ($items as $item) {
            $byId[$item->wcProductId] = $item;
        }

        self::assertInstanceOf(WooProduct::class, $byId[102]);
        self::assertInstanceOf(WooProductVariation::class, $byId[201]);
        self::assertSame(102, $byId[201]->parentId);
    }
}
