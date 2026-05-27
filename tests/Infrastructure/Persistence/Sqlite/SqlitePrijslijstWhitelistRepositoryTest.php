<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqlitePrijslijstWhitelistRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\PrijslijstWhitelistRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqlitePrijslijstWhitelistRepositoryTest extends PrijslijstWhitelistRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function makeRepository(): PrijslijstWhitelistRepository
    {
        return new SqlitePrijslijstWhitelistRepository(TestDatabase::pdo());
    }
}
