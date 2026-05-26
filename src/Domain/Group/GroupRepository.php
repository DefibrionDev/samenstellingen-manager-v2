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
}
