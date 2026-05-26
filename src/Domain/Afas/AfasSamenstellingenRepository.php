<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasSamenstellingenRepository
{
    /**
     * Vervang de hele lokale snapshot atomisch. Detecteert intern duplicates (identieke BOMs):
     * laagste itemcode wordt canonical, rest krijgt `duplicateOfItemcode` ingevuld.
     *
     * @param list<AfasSamenstelling> $samenstellingen
     */
    public function replaceSnapshot(array $samenstellingen): void;

    /**
     * @return list<AfasSamenstelling>
     */
    public function findAll(): array;

    /**
     * Alleen canonicals (duplicate_of_itemcode IS NULL).
     *
     * @return list<AfasSamenstelling>
     */
    public function findAllCanonical(): array;

    /**
     * Alleen duplicates (duplicate_of_itemcode IS NOT NULL).
     *
     * @return list<AfasSamenstelling>
     */
    public function findAllDuplicates(): array;

    public function countSnapshot(): int;

    public function findByItemcode(string $itemcode): ?AfasSamenstelling;
}
