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

    /**
     * Wijzig alleen de delta van een bestaande accessoire.
     *
     * @throws AccessoireNotFoundException wanneer de itemcode niet bestaat.
     */
    public function updateDelta(string $itemcode, int $deltaCents): void;

    /**
     * Wijzig één van de korte canonical namen (nl/fr/en) — gebruikt door
     * `accessoire:set-naam-kort`. `$naam` mag null zijn om te wissen.
     *
     * @param 'nl'|'fr'|'en' $taal
     * @throws AccessoireNotFoundException wanneer de itemcode niet bestaat.
     */
    public function updateNaamKort(string $itemcode, string $taal, ?string $naam): void;
}
