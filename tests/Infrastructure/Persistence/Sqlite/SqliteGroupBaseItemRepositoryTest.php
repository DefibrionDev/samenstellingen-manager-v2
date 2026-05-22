<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupBaseItemRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteGroupBaseItemRepositoryTest extends GroupBaseItemRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        TestDatabase::truncate();
        $pdo = TestDatabase::pdo();

        return [
            'groups' => new SqliteGroupRepository($pdo),
            'bases' => new SqliteGroupBaseRepository($pdo),
            'items' => new SqliteGroupBaseItemRepository($pdo),
        ];
    }
}
