<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
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
}
