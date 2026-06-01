<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteWebsiteRepository;
use Defibrion\Samenstellingen\Tests\Domain\Website\WebsiteRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteWebsiteRepositoryTest extends WebsiteRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function repository(): WebsiteRepository
    {
        return new SqliteWebsiteRepository(TestDatabase::pdo());
    }
}
