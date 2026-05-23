<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface BomBlacklistRepository
{
    /**
     * @throws BomCodeAlreadyBlacklistedException wanneer er al een entry met deze itemcode bestaat.
     */
    public function save(BomBlacklistEntry $entry): void;

    public function findByItemcode(string $itemcode): ?BomBlacklistEntry;

    /**
     * @return list<BomBlacklistEntry>
     */
    public function findAll(): array;
}
