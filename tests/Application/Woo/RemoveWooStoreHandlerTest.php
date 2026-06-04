<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Woo;

use Defibrion\Samenstellingen\Application\Woo\RemoveWooStore;
use Defibrion\Samenstellingen\Application\Woo\RemoveWooStoreHandler;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooCommerceStoreRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveWooStoreHandlerTest extends TestCase
{
    #[Test]
    public function deletesStoreByName(): void
    {
        $repo = new InMemoryWooCommerceStoreRepository();
        $saved = $repo->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        self::assertNotNull($saved->id);
        $handler = new RemoveWooStoreHandler($repo);

        $deletedId = $handler(new RemoveWooStore('defibrion.nl'));

        self::assertSame($saved->id, $deletedId);
        self::assertNull($repo->findByName('defibrion.nl'));
    }

    #[Test]
    public function throwsWhenStoreNotFound(): void
    {
        $handler = new RemoveWooStoreHandler(new InMemoryWooCommerceStoreRepository());

        $this->expectException(WooCommerceStoreNotFoundException::class);

        $handler(new RemoveWooStore('niet-bestaand.nl'));
    }
}
