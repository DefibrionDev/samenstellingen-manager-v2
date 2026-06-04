<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteWooCommerceStoreRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteWooProductRepository;
use Defibrion\Samenstellingen\Tests\Domain\Woo\WooProductRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteWooProductRepositoryTest extends WooProductRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        $pdo = TestDatabase::pdo();
        $pdo->exec('DELETE FROM woocommerce_products');
        $pdo->exec('DELETE FROM woocommerce_stores');

        return [
            'stores' => new SqliteWooCommerceStoreRepository($pdo),
            'products' => new SqliteWooProductRepository($pdo),
        ];
    }
}
