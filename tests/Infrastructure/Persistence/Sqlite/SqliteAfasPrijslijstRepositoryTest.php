<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\AfasPrijslijstRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteAfasPrijslijstRepositoryTest extends AfasPrijslijstRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function makeRepository(): AfasPrijslijstRepository
    {
        return new SqliteAfasPrijslijstRepository(TestDatabase::pdo());
    }
}
