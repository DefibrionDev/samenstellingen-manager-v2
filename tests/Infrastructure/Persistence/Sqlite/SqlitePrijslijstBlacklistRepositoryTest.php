<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqlitePrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\PrijslijstBlacklistRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqlitePrijslijstBlacklistRepositoryTest extends PrijslijstBlacklistRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function makeRepository(): PrijslijstBlacklistRepository
    {
        return new SqlitePrijslijstBlacklistRepository(TestDatabase::pdo());
    }
}
