<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Afas\BomCodeAlreadyBlacklistedException;

final class InMemoryBomBlacklistRepository implements BomBlacklistRepository
{
    /** @var array<string, BomBlacklistEntry> */
    private array $byItemcode = [];

    public function save(BomBlacklistEntry $entry): void
    {
        if (isset($this->byItemcode[$entry->itemcode])) {
            throw BomCodeAlreadyBlacklistedException::forItemcode($entry->itemcode);
        }

        $this->byItemcode[$entry->itemcode] = $entry;
    }

    public function findByItemcode(string $itemcode): ?BomBlacklistEntry
    {
        return $this->byItemcode[$itemcode] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->byItemcode);
    }
}
