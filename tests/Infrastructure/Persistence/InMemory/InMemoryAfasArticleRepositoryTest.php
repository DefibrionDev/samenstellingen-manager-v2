<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasArticleRepository;
use Defibrion\Samenstellingen\Tests\Domain\Afas\AfasArticleRepositoryContractTestCase;

final class InMemoryAfasArticleRepositoryTest extends AfasArticleRepositoryContractTestCase
{
    protected function makeRepository(): AfasArticleRepository
    {
        return new InMemoryAfasArticleRepository();
    }
}
