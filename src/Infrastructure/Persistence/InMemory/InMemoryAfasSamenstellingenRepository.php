<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;

final class InMemoryAfasSamenstellingenRepository implements AfasSamenstellingenRepository
{
    /** @var list<AfasSamenstelling> */
    private array $samenstellingen = [];

    public function replaceSnapshot(array $samenstellingen): void
    {
        $this->samenstellingen = $samenstellingen;
    }

    public function findAll(): array
    {
        return $this->samenstellingen;
    }

    public function countSnapshot(): int
    {
        return count($this->samenstellingen);
    }
}
