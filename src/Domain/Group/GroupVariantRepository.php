<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupVariantRepository
{
    /**
     * @throws GroupNotFoundException
     */
    public function regenerateForGroup(string $familyHeadItemcode): void;

    /**
     * @throws GroupNotFoundException
     *
     * @return list<GroupVariant>
     */
    public function findAllForGroup(string $familyHeadItemcode): array;

    public function markMatched(int $variantId, string $afasItemcode): void;

    public function markNoMatch(int $variantId): void;
}
