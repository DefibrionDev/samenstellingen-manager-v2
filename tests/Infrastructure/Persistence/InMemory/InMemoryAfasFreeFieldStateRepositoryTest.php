<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasFreeFieldStateRepository;
use Defibrion\Samenstellingen\Tests\Application\Publications\AfasFreeFieldStateRepositoryContractTestCase;

final class InMemoryAfasFreeFieldStateRepositoryTest extends AfasFreeFieldStateRepositoryContractTestCase
{
    protected function makeRepository(): AfasFreeFieldStateRepository
    {
        return new InMemoryAfasFreeFieldStateRepository();
    }
}
