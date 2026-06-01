<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBasePublicationRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWebsiteRepository;
use Defibrion\Samenstellingen\Tests\Domain\Website\BasePublicationRepositoryContractTestCase;

final class InMemoryBasePublicationRepositoryTest extends BasePublicationRepositoryContractTestCase
{
    /**
     * @return array{groups: GroupRepository, bases: GroupBaseRepository, websites: WebsiteRepository, publications: BasePublicationRepository}
     */
    protected function makeRepositories(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();

        return [
            'groups' => $groups,
            'bases' => $bases,
            'websites' => $websites,
            'publications' => $publications,
        ];
    }
}
