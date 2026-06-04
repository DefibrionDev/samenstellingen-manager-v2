<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooCommerceStoreRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooProductRepository;
use Defibrion\Samenstellingen\Tests\Domain\Woo\WooProductRepositoryContractTestCase;

final class InMemoryWooProductRepositoryTest extends WooProductRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        return [
            'stores' => new InMemoryWooCommerceStoreRepository(),
            'products' => new InMemoryWooProductRepository(),
        ];
    }
}
