<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\PrijslijstBlacklistRepositoryContractTestCase;

final class InMemoryPrijslijstBlacklistRepositoryTest extends PrijslijstBlacklistRepositoryContractTestCase
{
    protected function makeRepository(): PrijslijstBlacklistRepository
    {
        return new InMemoryPrijslijstBlacklistRepository();
    }
}
