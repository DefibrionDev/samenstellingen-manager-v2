<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteWooCommerceStoreRepository;
use Defibrion\Samenstellingen\Tests\Domain\Woo\WooCommerceStoreRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteWooCommerceStoreRepositoryTest extends WooCommerceStoreRepositoryContractTestCase
{
    protected function makeRepository(): WooCommerceStoreRepository
    {
        $pdo = TestDatabase::pdo();
        $pdo->exec('DELETE FROM woocommerce_products');
        $pdo->exec('DELETE FROM woocommerce_stores');

        return new SqliteWooCommerceStoreRepository($pdo);
    }
}
