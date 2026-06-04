<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Woo;

use Defibrion\Samenstellingen\Application\Woo\AddWooStore;
use Defibrion\Samenstellingen\Application\Woo\AddWooStoreHandler;
use Defibrion\Samenstellingen\Application\Woo\InvalidWooStoreException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooCommerceStoreRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddWooStoreHandlerTest extends TestCase
{
    #[Test]
    public function savesStoreAndReturnsItWithId(): void
    {
        $repo = new InMemoryWooCommerceStoreRepository();
        $handler = new AddWooStoreHandler($repo);

        $store = $handler(new AddWooStore('defibrion.nl', 'https://defibrion.nl', 'ck_x', 'cs_y'));

        self::assertNotNull($store->id);
        self::assertSame('defibrion.nl', $store->name);
        self::assertSame('_afas_itemcode', $store->afasItemcodeMetaKey);
        self::assertNotNull($repo->findByName('defibrion.nl'));
    }

    #[Test]
    public function respectsCustomMetaKey(): void
    {
        $repo = new InMemoryWooCommerceStoreRepository();
        $handler = new AddWooStoreHandler($repo);

        $store = $handler(new AddWooStore('defibrion.fr', 'https://defibrion.fr', 'ck', 'cs', 'afas_item_nummer'));

        self::assertSame('afas_item_nummer', $store->afasItemcodeMetaKey);
    }

    #[Test]
    public function trimsTrailingSlashFromBaseUrl(): void
    {
        $repo = new InMemoryWooCommerceStoreRepository();
        $handler = new AddWooStoreHandler($repo);

        $store = $handler(new AddWooStore('defibrion.nl', 'https://defibrion.nl/', 'ck', 'cs'));

        self::assertSame('https://defibrion.nl', $store->baseUrl);
    }

    #[Test]
    public function rejectsNonHttpsUrl(): void
    {
        $handler = new AddWooStoreHandler(new InMemoryWooCommerceStoreRepository());

        $this->expectException(InvalidWooStoreException::class);

        $handler(new AddWooStore('local', 'http://localhost', 'ck', 'cs'));
    }

    #[Test]
    public function rejectsEmptyName(): void
    {
        $handler = new AddWooStoreHandler(new InMemoryWooCommerceStoreRepository());

        $this->expectException(InvalidWooStoreException::class);

        $handler(new AddWooStore('', 'https://defibrion.nl', 'ck', 'cs'));
    }

    #[Test]
    public function rejectsEmptyConsumerKey(): void
    {
        $handler = new AddWooStoreHandler(new InMemoryWooCommerceStoreRepository());

        $this->expectException(InvalidWooStoreException::class);

        $handler(new AddWooStore('defibrion.nl', 'https://defibrion.nl', '', 'cs'));
    }

    #[Test]
    public function rejectsEmptyConsumerSecret(): void
    {
        $handler = new AddWooStoreHandler(new InMemoryWooCommerceStoreRepository());

        $this->expectException(InvalidWooStoreException::class);

        $handler(new AddWooStore('defibrion.nl', 'https://defibrion.nl', 'ck', ''));
    }

    #[Test]
    public function rejectsDuplicateName(): void
    {
        $repo = new InMemoryWooCommerceStoreRepository();
        $repo->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        $handler = new AddWooStoreHandler($repo);

        $this->expectException(InvalidWooStoreException::class);

        $handler(new AddWooStore('defibrion.nl', 'https://defibrion.nl', 'ck2', 'cs2'));
    }
}
