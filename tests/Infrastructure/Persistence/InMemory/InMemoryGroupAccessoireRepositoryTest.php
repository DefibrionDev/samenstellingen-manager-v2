<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupAccessoireRepositoryContractTestCase;

final class InMemoryGroupAccessoireRepositoryTest extends GroupAccessoireRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        $groups = new InMemoryGroupRepository();
        $accessoires = new InMemoryAccessoireRepository();

        return [
            'groups' => $groups,
            'accessoires' => $accessoires,
            'links' => new InMemoryGroupAccessoireRepository($groups, $accessoires),
        ];
    }
}
