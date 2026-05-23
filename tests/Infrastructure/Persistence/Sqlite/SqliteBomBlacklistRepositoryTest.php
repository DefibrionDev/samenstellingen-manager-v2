<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteBomBlacklistRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\BomBlacklistRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteBomBlacklistRepositoryTest extends BomBlacklistRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function makeRepository(): BomBlacklistRepository
    {
        return new SqliteBomBlacklistRepository(TestDatabase::pdo());
    }
}
