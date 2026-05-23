<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBomBlacklistRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\BomBlacklistRepositoryContractTestCase;

final class InMemoryBomBlacklistRepositoryTest extends BomBlacklistRepositoryContractTestCase
{
    protected function makeRepository(): BomBlacklistRepository
    {
        return new InMemoryBomBlacklistRepository();
    }
}
