<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupVariantRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupVariantRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteGroupVariantRepositoryTest extends GroupVariantRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        TestDatabase::truncate();
        $pdo = TestDatabase::pdo();

        return [
            'groups' => new SqliteGroupRepository($pdo),
            'bases' => new SqliteGroupBaseRepository($pdo),
            'accessoires' => new SqliteAccessoireRepository($pdo),
            'links' => new SqliteGroupAccessoireRepository($pdo),
            'variants' => new SqliteGroupVariantRepository($pdo),
        ];
    }
}
