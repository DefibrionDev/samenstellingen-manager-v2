<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface PrijslijstWhitelistRepository
{
    /**
     * @throws PrijslijstAlreadyWhitelistedException
     * @throws \InvalidArgumentException wanneer id of reden leeg is.
     */
    public function add(string $prijslijstId, string $reden): void;

    /**
     * @throws PrijslijstNotWhitelistedException
     */
    public function remove(string $prijslijstId): void;

    public function isBlacklisted(string $prijslijstId): bool;

    /**
     * @return list<PrijslijstWhitelistEntry>
     */
    public function findAll(): array;
}
