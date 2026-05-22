<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteGroupRepositoryTest extends GroupRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function repository(): GroupRepository
    {
        return new SqliteGroupRepository(TestDatabase::pdo());
    }
}
