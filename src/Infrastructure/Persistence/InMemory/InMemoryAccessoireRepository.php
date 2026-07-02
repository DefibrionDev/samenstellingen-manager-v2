<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;

final class InMemoryAccessoireRepository implements AccessoireRepository
{
    /** @var array<string, Accessoire> */
    private array $byItemcode = [];

    public function save(Accessoire $accessoire): void
    {
        if (isset($this->byItemcode[$accessoire->itemcode])) {
            throw AccessoireAlreadyExistsException::forItemcode($accessoire->itemcode);
        }

        $this->byItemcode[$accessoire->itemcode] = $accessoire;
    }

    public function findByItemcode(string $itemcode): ?Accessoire
    {
        return $this->byItemcode[$itemcode] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->byItemcode);
    }

    public function delete(string $itemcode): void
    {
        if (!isset($this->byItemcode[$itemcode])) {
            throw AccessoireNotFoundException::forItemcode($itemcode);
        }
        unset($this->byItemcode[$itemcode]);
    }

    public function updateDelta(string $itemcode, int $deltaCents): void
    {
        $existing = $this->byItemcode[$itemcode] ?? null;
        if ($existing === null) {
            throw AccessoireNotFoundException::forItemcode($itemcode);
        }
        $this->byItemcode[$itemcode] = new Accessoire(
            $existing->itemcode,
            $existing->label,
            $deltaCents,
            $existing->naamKortNl,
            $existing->naamKortFr,
            $existing->naamKortEn,
            $existing->naamKortDe,
        );
    }

    public function updateNaamKort(string $itemcode, string $taal, ?string $naam): void
    {
        $existing = $this->byItemcode[$itemcode] ?? null;
        if ($existing === null) {
            throw AccessoireNotFoundException::forItemcode($itemcode);
        }
        $clean = $naam !== null && trim($naam) !== '' ? trim($naam) : null;
        $this->byItemcode[$itemcode] = new Accessoire(
            $existing->itemcode,
            $existing->label,
            $existing->deltaCents,
            $taal === 'nl' ? $clean : $existing->naamKortNl,
            $taal === 'fr' ? $clean : $existing->naamKortFr,
            $taal === 'en' ? $clean : $existing->naamKortEn,
            $taal === 'de' ? $clean : $existing->naamKortDe,
        );
    }
}
