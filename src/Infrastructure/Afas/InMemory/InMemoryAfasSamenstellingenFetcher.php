<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenFetcher;

final class InMemoryAfasSamenstellingenFetcher implements AfasSamenstellingenFetcher
{
    /** @var list<AfasSamenstelling> */
    private array $samenstellingen = [];

    public function withSamenstellingen(AfasSamenstelling ...$samenstellingen): self
    {
        $clone = clone $this;
        $clone->samenstellingen = array_values($samenstellingen);

        return $clone;
    }

    public function fetchAll(): array
    {
        return $this->samenstellingen;
    }
}
