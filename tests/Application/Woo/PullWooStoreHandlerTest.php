<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Woo;

use Defibrion\Samenstellingen\Application\Woo\PullWooStore;
use Defibrion\Samenstellingen\Application\Woo\PullWooStoreHandler;
use Defibrion\Samenstellingen\Application\Woo\WooCommerceClientFactory;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceClient;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooCommerceStoreRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooProductRepository;
use Defibrion\Samenstellingen\Infrastructure\Woo\InMemoryWooCommerceClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PullWooStoreHandlerTest extends TestCase
{
    #[Test]
    public function pullsSimpleAndVariableProductsAndFlattensVariations(): void
    {
        $stores = new InMemoryWooCommerceStoreRepository();
        $products = new InMemoryWooProductRepository();
        $stored = $stores->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        self::assertNotNull($stored->id);

        $client = new InMemoryWooCommerceClient(
            products: [
                new WooProduct(101, 'simple', 'sku-101', 'Mindray semi', 'publish', null, '21011'),
                new WooProduct(102, 'variable', null, 'Mindray semi 4G parent', 'publish', null, null),
            ],
            variationsByParentId: [
                102 => [
                    new WooProductVariation(201, 102, 'sku-201', 'NL', 'publish', null, '21018-60110'),
                    new WooProductVariation(202, 102, 'sku-202', 'DE', 'publish', null, '21018-DE-60110'),
                ],
            ],
        );

        $handler = new PullWooStoreHandler($stores, $products, $this->factoryFor($client));

        $result = $handler(new PullWooStore(null));

        self::assertSame(['defibrion.nl' => 4], $result->itemsByStore);
        self::assertCount(4, $products->findAllForStore($stored->id));
    }

    #[Test]
    public function replacesPreviousSnapshotForSameStore(): void
    {
        $stores = new InMemoryWooCommerceStoreRepository();
        $products = new InMemoryWooProductRepository();
        $stored = $stores->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        self::assertNotNull($stored->id);
        $products->replaceForStore($stored->id, [
            new WooProduct(999, 'simple', null, 'oud', 'publish', null, '99999'),
        ]);

        $client = new InMemoryWooCommerceClient(products: [
            new WooProduct(101, 'simple', null, 'nieuw', 'publish', null, '21011'),
        ]);

        $handler = new PullWooStoreHandler($stores, $products, $this->factoryFor($client));

        $handler(new PullWooStore(null));

        $items = $products->findAllForStore($stored->id);
        self::assertCount(1, $items);
        self::assertSame(101, $items[0]->wcProductId);
    }

    #[Test]
    public function pullsOnlyRequestedStoreWhenNameGiven(): void
    {
        $stores = new InMemoryWooCommerceStoreRepository();
        $products = new InMemoryWooProductRepository();
        $nl = $stores->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        $fr = $stores->save(new WooCommerceStore(null, 'defibrion.fr', 'https://defibrion.fr', 'ck', 'cs'));
        self::assertNotNull($nl->id);
        self::assertNotNull($fr->id);

        $factory = new class () implements WooCommerceClientFactory {
            public function forStore(WooCommerceStore $store): WooCommerceClient
            {
                return new InMemoryWooCommerceClient(products: [
                    new WooProduct(1, 'simple', null, 'p-' . $store->name, 'publish', null, 'AFAS-' . $store->name),
                ]);
            }
        };
        $handler = new PullWooStoreHandler($stores, $products, $factory);

        $result = $handler(new PullWooStore('defibrion.fr'));

        self::assertSame(['defibrion.fr' => 1], $result->itemsByStore);
        self::assertCount(0, $products->findAllForStore($nl->id));
        self::assertCount(1, $products->findAllForStore($fr->id));
    }

    #[Test]
    public function rejectsUnknownStoreName(): void
    {
        $handler = new PullWooStoreHandler(
            new InMemoryWooCommerceStoreRepository(),
            new InMemoryWooProductRepository(),
            $this->factoryFor(new InMemoryWooCommerceClient()),
        );

        $this->expectException(WooCommerceStoreNotFoundException::class);

        $handler(new PullWooStore('nope.nl'));
    }

    #[Test]
    public function emptyStoreSetResultsInEmptyResult(): void
    {
        $handler = new PullWooStoreHandler(
            new InMemoryWooCommerceStoreRepository(),
            new InMemoryWooProductRepository(),
            $this->factoryFor(new InMemoryWooCommerceClient()),
        );

        $result = $handler(new PullWooStore(null));

        self::assertSame([], $result->itemsByStore);
    }

    private function factoryFor(WooCommerceClient $client): WooCommerceClientFactory
    {
        return new class ($client) implements WooCommerceClientFactory {
            public function __construct(private WooCommerceClient $client)
            {
            }

            public function forStore(WooCommerceStore $store): WooCommerceClient
            {
                return $this->client;
            }
        };
    }
}
