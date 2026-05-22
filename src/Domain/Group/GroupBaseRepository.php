<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupBaseRepository
{
    /**
     * @throws GroupNotFoundException     wanneer de groep niet bestaat.
     * @throws BaseAlreadyExistsException wanneer een base met deze naam al in deze groep zit.
     */
    public function saveForGroup(string $familyHeadItemcode, GroupBase $base): GroupBase;

    public function findById(int $baseId): ?GroupBase;

    /**
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     *
     * @return list<GroupBase>
     */
    public function findAllForGroup(string $familyHeadItemcode): array;
}
