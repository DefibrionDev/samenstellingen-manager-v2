<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Tests\Domain\Group\GroupRepositoryContractTestCase;

final class InMemoryGroupRepositoryTest extends GroupRepositoryContractTestCase
{
    protected function repository(): GroupRepository
    {
        return new InMemoryGroupRepository();
    }
}
