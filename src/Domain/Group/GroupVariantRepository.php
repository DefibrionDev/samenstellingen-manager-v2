<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupVariantRepository
{
    /**
     * Synchroniseer de variantmatrix voor een groep met het cartesisch product
     * van bases × {null ∪ accessoires}. Idempotent: ontbrekende rijen worden
     * toegevoegd, bestaande rijen (incl. eventuele AFAS-koppeling) blijven staan.
     *
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     */
    public function regenerateForGroup(string $groupName): void;

    /**
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     *
     * @return list<GroupVariant>
     */
    public function findAllForGroup(string $groupName): array;
}
