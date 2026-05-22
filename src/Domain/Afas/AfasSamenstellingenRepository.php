<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasSamenstellingenRepository
{
    /**
     * Vervang de hele lokale snapshot atomisch met de meegegeven samenstellingen.
     *
     * @param list<AfasSamenstelling> $samenstellingen
     */
    public function replaceSnapshot(array $samenstellingen): void;

    /**
     * @return list<AfasSamenstelling>
     */
    public function findAll(): array;

    public function countSnapshot(): int;
}
