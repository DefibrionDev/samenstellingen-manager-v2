<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;

final class InMemoryAfasPrijslijstRepository implements AfasPrijslijstRepository
{
    /** @var array<string, AfasPrijslijst> */
    private array $prijslijsten = [];

    public function replaceSnapshot(array $prijslijsten): void
    {
        $this->prijslijsten = [];
        foreach ($prijslijsten as $p) {
            $this->prijslijsten[$p->id] = $p;
        }
    }

    public function findAll(): array
    {
        $sorted = $this->prijslijsten;
        ksort($sorted);

        return array_values($sorted);
    }

    public function findById(string $id): ?AfasPrijslijst
    {
        return $this->prijslijsten[$id] ?? null;
    }

    public function countSnapshot(): int
    {
        return count($this->prijslijsten);
    }
}
