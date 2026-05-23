<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Accessoire;

interface AccessoireRepository
{
    /**
     * @throws AccessoireAlreadyExistsException wanneer er al een accessoire met deze itemcode bestaat.
     */
    public function save(Accessoire $accessoire): void;

    public function findByItemcode(string $itemcode): ?Accessoire;

    /**
     * @return list<Accessoire>
     */
    public function findAll(): array;

    /**
     * @throws AccessoireNotFoundException wanneer de itemcode niet in de catalogus staat.
     */
    public function delete(string $itemcode): void;
}
