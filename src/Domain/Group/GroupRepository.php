<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupRepository
{
    /**
     * @throws GroupAlreadyExistsException wanneer een groep met dezelfde naam of family-head itemcode al bestaat.
     */
    public function save(Group $group): void;

    public function findByName(string $name): ?Group;

    public function findByFamilyHeadItemcode(string $familyHeadItemcode): ?Group;

    /**
     * @return list<Group>
     */
    public function findAll(): array;

    /**
     * Verwijder een groep + alles wat eraan hangt (cascade via FK: bases, base-items,
     * variants, group_accessoires-koppelingen). De accessoires-catalogus blijft.
     * Idempotent: onbekende family-head is no-op.
     */
    public function delete(string $familyHeadItemcode): void;

    /**
     * Wijzig één van de model-namen (nl/fr/en) — gebruikt door
     * `group:set-model-naam`. `$naam` mag null zijn om te wissen.
     *
     * @param 'nl'|'fr'|'en' $taal
     * @throws GroupNotFoundException wanneer de family-head niet bestaat.
     */
    public function updateModelNaam(string $familyHeadItemcode, string $taal, ?string $naam): void;

    /**
     * Verschuif de `family_head_itemcode` van een bestaande groep. Bases blijven
     * gekoppeld via group_id (FK), dus die migreren automatisch mee. Gebruikt
     * door de auto-shift-detectie tijdens `afas:pull` (zie PLAN.md §23).
     *
     * @throws GroupNotFoundException wanneer `$oldFamilyHead` niet bestaat.
     * @throws GroupAlreadyExistsException wanneer `$newFamilyHead` al claimt.
     */
    public function updateFamilyHeadItemcode(string $oldFamilyHead, string $newFamilyHead): void;
}
