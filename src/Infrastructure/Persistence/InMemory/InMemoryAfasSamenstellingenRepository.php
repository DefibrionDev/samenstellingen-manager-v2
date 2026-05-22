<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Afas\DuplicateDetector;

final class InMemoryAfasSamenstellingenRepository implements AfasSamenstellingenRepository
{
    /** @var list<AfasSamenstelling> */
    private array $samenstellingen = [];

    private DuplicateDetector $detector;

    public function __construct()
    {
        $this->detector = new DuplicateDetector();
    }

    public function replaceSnapshot(array $samenstellingen): void
    {
        $this->samenstellingen = $this->detector->annotate($samenstellingen);
    }

    public function findAll(): array
    {
        return $this->samenstellingen;
    }

    public function findAllCanonical(): array
    {
        return array_values(array_filter(
            $this->samenstellingen,
            static fn (AfasSamenstelling $s): bool => $s->isCanonical(),
        ));
    }

    public function findAllDuplicates(): array
    {
        return array_values(array_filter(
            $this->samenstellingen,
            static fn (AfasSamenstelling $s): bool => !$s->isCanonical(),
        ));
    }

    public function countSnapshot(): int
    {
        return count($this->samenstellingen);
    }
}
