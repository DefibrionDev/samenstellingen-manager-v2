<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasArticleRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\AfasArticleRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteAfasArticleRepositoryTest extends AfasArticleRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    protected function makeRepository(): AfasArticleRepository
    {
        return new SqliteAfasArticleRepository(TestDatabase::pdo());
    }
}
