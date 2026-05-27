<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstAlreadyWhitelistedException;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstNotWhitelistedException;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistEntry;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;

final class InMemoryPrijslijstWhitelistRepository implements PrijslijstWhitelistRepository
{
    /** @var array<string, PrijslijstWhitelistEntry> */
    private array $entries = [];

    public function add(string $prijslijstId, string $reden): void
    {
        if (isset($this->entries[$prijslijstId])) {
            throw PrijslijstAlreadyWhitelistedException::forId($prijslijstId);
        }
        $this->entries[$prijslijstId] = new PrijslijstWhitelistEntry(
            $prijslijstId,
            $reden,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        );
    }

    public function remove(string $prijslijstId): void
    {
        if (!isset($this->entries[$prijslijstId])) {
            throw PrijslijstNotWhitelistedException::forId($prijslijstId);
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
