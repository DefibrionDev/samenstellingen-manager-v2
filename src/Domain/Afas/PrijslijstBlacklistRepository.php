<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface PrijslijstBlacklistRepository
{
    /**
     * @throws PrijslijstAlreadyBlacklistedException
     * @throws \InvalidArgumentException wanneer id of reden leeg is.
     */
    public function add(string $prijslijstId, string $reden): void;

    /**
     * @throws PrijslijstNotBlacklistedException
     */
    public function remove(string $prijslijstId): void;

    public function isBlacklisted(string $prijslijstId): bool;

    /**
     * @return list<PrijslijstBlacklistEntry>
     */
    public function findAll(): array;
}
