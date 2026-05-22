<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Tests\Domain\Accessoire\AccessoireRepositoryContractTestCase;

final class InMemoryAccessoireRepositoryTest extends AccessoireRepositoryContractTestCase
{
    protected function makeRepository(): AccessoireRepository
    {
        return new InMemoryAccessoireRepository();
    }
}
