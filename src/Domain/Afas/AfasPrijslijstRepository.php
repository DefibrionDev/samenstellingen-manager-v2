<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasPrijslijstRepository
{
    /**
     * Vervang de hele lokale snapshot atomisch.
     *
     * @param list<AfasPrijslijst> $prijslijsten
     */
    public function replaceSnapshot(array $prijslijsten): void;

    /**
     * @return list<AfasPrijslijst>
     */
    public function findAll(): array;

    public function findById(string $id): ?AfasPrijslijst;

    public function countSnapshot(): int;
}
