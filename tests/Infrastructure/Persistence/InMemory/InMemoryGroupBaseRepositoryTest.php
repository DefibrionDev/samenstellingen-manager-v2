<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupBaseRepositoryContractTestCase;

final class InMemoryGroupBaseRepositoryTest extends GroupBaseRepositoryContractTestCase
{
    protected function makeRepositories(): array
    {
        $groups = new InMemoryGroupRepository();

        return [
            'groups' => $groups,
            'bases' => new InMemoryGroupBaseRepository($groups),
        ];
    }
}
