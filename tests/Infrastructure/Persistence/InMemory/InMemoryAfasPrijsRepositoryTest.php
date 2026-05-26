<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\AfasPrijsRepositoryContractTestCase;

final class InMemoryAfasPrijsRepositoryTest extends AfasPrijsRepositoryContractTestCase
{
    protected function makeRepository(): AfasPrijsRepository
    {
        return new InMemoryAfasPrijsRepository();
    }
}
