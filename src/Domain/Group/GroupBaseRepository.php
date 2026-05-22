<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupBaseRepository
{
    /**
     * @throws GroupNotFoundException        wanneer de groep niet bestaat.
     * @throws BaseAlreadyExistsException    wanneer een base met deze itemcode al in deze groep zit.
     */
    public function saveForGroup(string $groupName, GroupBase $base): void;

    /**
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     *
     * @return list<GroupBase>
     */
    public function findAllForGroup(string $groupName): array;
}
