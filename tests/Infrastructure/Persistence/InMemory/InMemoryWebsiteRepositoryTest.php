<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWebsiteRepository;
use Defibrion\Samenstellingen\Tests\Domain\Website\WebsiteRepositoryContractTestCase;

final class InMemoryWebsiteRepositoryTest extends WebsiteRepositoryContractTestCase
{
    protected function repository(): WebsiteRepository
    {
        return new InMemoryWebsiteRepository();
    }
}
