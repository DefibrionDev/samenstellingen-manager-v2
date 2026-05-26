<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasPrijsRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\AfasPrijsRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteAfasPrijsRepositoryTest extends AfasPrijsRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function makeRepository(): AfasPrijsRepository
    {
        return new SqliteAfasPrijsRepository(TestDatabase::pdo());
    }
}
