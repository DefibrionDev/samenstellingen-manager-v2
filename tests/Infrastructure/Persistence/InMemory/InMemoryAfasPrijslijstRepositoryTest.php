<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\AfasPrijslijstRepositoryContractTestCase;

final class InMemoryAfasPrijslijstRepositoryTest extends AfasPrijslijstRepositoryContractTestCase
{
    protected function makeRepository(): AfasPrijslijstRepository
    {
        return new InMemoryAfasPrijslijstRepository();
    }
}
