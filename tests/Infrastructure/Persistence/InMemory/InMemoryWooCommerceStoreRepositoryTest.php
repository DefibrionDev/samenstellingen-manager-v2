<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooCommerceStoreRepository;
use Defibrion\Samenstellingen\Tests\Domain\Woo\WooCommerceStoreRepositoryContractTestCase;

final class InMemoryWooCommerceStoreRepositoryTest extends WooCommerceStoreRepositoryContractTestCase
{
    protected function makeRepository(): WooCommerceStoreRepository
    {
        return new InMemoryWooCommerceStoreRepository();
    }
}
