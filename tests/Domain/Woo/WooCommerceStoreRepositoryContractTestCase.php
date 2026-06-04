<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Woo;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class WooCommerceStoreRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): WooCommerceStoreRepository;

    #[Test]
    public function savePersistsAndAssignsId(): void
    {
        $repo = $this->makeRepository();

        $saved = $repo->save(new WooCommerceStore(
            null,
            'defibrion.nl',
            'https://defibrion.nl',
            'ck_abc',
            'cs_def',
        ));

        self::assertNotNull($saved->id);
        self::assertSame('defibrion.nl', $saved->name);
        self::assertSame('_afas_itemcode', $saved->afasItemcodeMetaKey);
    }

    #[Test]
    public function savePreservesCustomMetaKey(): void
    {
        $repo = $this->makeRepository();

        $saved = $repo->save(new WooCommerceStore(
            null,
            'defibrion.fr',
            'https://defibrion.fr',
            'ck_x',
            'cs_y',
            'afas_item_nummer',
        ));

        self::assertSame('afas_item_nummer', $saved->afasItemcodeMetaKey);

        $loaded = $repo->findByName('defibrion.fr');
        self::assertNotNull($loaded);
        self::assertSame('afas_item_nummer', $loaded->afasItemcodeMetaKey);
    }

    #[Test]
    public function findByNameReturnsNullWhenAbsent(): void
    {
        $repo = $this->makeRepository();

        self::assertNull($repo->findByName('nope'));
    }

    #[Test]
    public function findAllReturnsAllStoresOrderedByName(): void
    {
        $repo = $this->makeRepository();
        $repo->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck1', 'cs1'));
        $repo->save(new WooCommerceStore(null, 'defibrion.be', 'https://defibrion.be', 'ck2', 'cs2'));
        $repo->save(new WooCommerceStore(null, 'defibrion.fr', 'https://defibrion.fr', 'ck3', 'cs3'));

        $all = $repo->findAll();

        self::assertCount(3, $all);
        self::assertSame(['defibrion.be', 'defibrion.fr', 'defibrion.nl'], array_map(static fn ($s) => $s->name, $all));
    }

    #[Test]
    public function deleteRemovesStore(): void
    {
        $repo = $this->makeRepository();
        $saved = $repo->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        self::assertNotNull($saved->id);

        $repo->delete($saved->id);

        self::assertNull($repo->findByName('defibrion.nl'));
        self::assertSame([], $repo->findAll());
    }

    #[Test]
    public function deleteUnknownIdThrows(): void
    {
        $repo = $this->makeRepository();

        $this->expectException(WooCommerceStoreNotFoundException::class);

        $repo->delete(9999);
    }

    #[Test]
    public function saveWithDuplicateNameThrows(): void
    {
        $repo = $this->makeRepository();
        $repo->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));

        $this->expectException(\Throwable::class);

        $repo->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck2', 'cs2'));
    }
}
