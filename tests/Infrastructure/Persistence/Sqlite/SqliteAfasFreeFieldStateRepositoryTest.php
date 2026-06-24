<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasFreeFieldStateRepository;
use Defibrion\Samenstellingen\Tests\Application\Publications\AfasFreeFieldStateRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteAfasFreeFieldStateRepositoryTest extends AfasFreeFieldStateRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::pdo()->exec('DELETE FROM afas_free_field_state');
    }

    protected function makeRepository(): AfasFreeFieldStateRepository
    {
        return new SqliteAfasFreeFieldStateRepository(TestDatabase::pdo());
    }
}
