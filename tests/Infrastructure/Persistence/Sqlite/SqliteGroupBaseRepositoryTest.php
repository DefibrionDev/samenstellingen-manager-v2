<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupBaseRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteGroupBaseRepositoryTest extends GroupBaseRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        TestDatabase::truncate();
        $pdo = TestDatabase::pdo();

        return [
            'groups' => new SqliteGroupRepository($pdo),
            'bases' => new SqliteGroupBaseRepository($pdo),
        ];
    }
}
