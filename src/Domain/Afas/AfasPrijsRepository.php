<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasPrijsRepository
{
    /**
     * Vervang de hele lokale snapshot atomisch.
     *
     * @param list<AfasPrijs> $prijzen
     */
    public function replaceSnapshot(array $prijzen): void;

    /**
     * @return list<AfasPrijs>
     */
    public function findByItemcode(string $itemcode): array;

    public function countSnapshot(): int;
}
