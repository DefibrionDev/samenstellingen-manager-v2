<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstAlreadyBlacklistedException;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstNotBlacklistedException;

final class InMemoryPrijslijstBlacklistRepository implements PrijslijstBlacklistRepository
{
    /** @var array<string, PrijslijstBlacklistEntry> */
    private array $entries = [];

    public function add(string $prijslijstId, string $reden): void
    {
        if (isset($this->entries[$prijslijstId])) {
            throw PrijslijstAlreadyBlacklistedException::forId($prijslijstId);
        }
        $this->entries[$prijslijstId] = new PrijslijstBlacklistEntry(
            $prijslijstId,
            $reden,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        );
    }

    public function remove(string $prijslijstId): void
    {
        if (!isset($this->entries[$prijslijstId])) {
            throw PrijslijstNotBlacklistedException::forId($prijslijstId);
        }
        unset($this->entries[$prijslijstId]);
    }

    public function isBlacklisted(string $prijslijstId): bool
    {
        return isset($this->entries[$prijslijstId]);
    }

    public function findAll(): array
    {
        $sorted = $this->entries;
        ksort($sorted);

        return array_values($sorted);
    }
}
