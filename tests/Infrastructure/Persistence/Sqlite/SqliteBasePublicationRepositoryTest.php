<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteBasePublicationRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteWebsiteRepository;
use Defibrion\Samenstellingen\Tests\Domain\Website\BasePublicationRepositoryContractTestCase;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;

final class SqliteBasePublicationRepositoryTest extends BasePublicationRepositoryContractTestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
        parent::setUp();
    }

    /**
     * @return array{groups: GroupRepository, bases: GroupBaseRepository, websites: WebsiteRepository, publications: BasePublicationRepository}
     */
    protected function makeRepositories(): array
    {
        $pdo = TestDatabase::pdo();

        return [
            'groups' => new SqliteGroupRepository($pdo),
            'bases' => new SqliteGroupBaseRepository($pdo),
            'websites' => new SqliteWebsiteRepository($pdo),
            'publications' => new SqliteBasePublicationRepository($pdo),
        ];
    }
}
