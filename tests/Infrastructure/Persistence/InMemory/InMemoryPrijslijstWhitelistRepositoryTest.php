<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstWhitelistRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\PrijslijstWhitelistRepositoryContractTestCase;

final class InMemoryPrijslijstWhitelistRepositoryTest extends PrijslijstWhitelistRepositoryContractTestCase
{
    protected function makeRepository(): PrijslijstWhitelistRepository
    {
        return new InMemoryPrijslijstWhitelistRepository();
    }
}
