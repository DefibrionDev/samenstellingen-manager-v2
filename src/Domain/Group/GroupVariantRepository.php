<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupVariantRepository
{
    /**
     * Synchroniseer de variantmatrix voor een groep met het cartesisch product
     * van bases × {null ∪ accessoires}. Idempotent.
     *
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     */
    public function regenerateForGroup(string $familyHeadItemcode): void;

    /**
     * @throws GroupNotFoundException
     *
     * @return list<GroupVariant>
     */
    public function findAllForGroup(string $familyHeadItemcode): array;
}
