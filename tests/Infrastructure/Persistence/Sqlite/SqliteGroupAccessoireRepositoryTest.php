<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupAccessoireRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteGroupAccessoireRepositoryTest extends GroupAccessoireRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        TestDatabase::truncate();
        $pdo = TestDatabase::pdo();

        return [
            'groups' => new SqliteGroupRepository($pdo),
            'accessoires' => new SqliteAccessoireRepository($pdo),
            'links' => new SqliteGroupAccessoireRepository($pdo),
        ];
    }
}
