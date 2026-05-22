<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;

interface GroupAccessoireRepository
{
    /**
     * @throws GroupNotFoundException               wanneer de groep niet bestaat.
     * @throws AccessoireNotFoundException          wanneer de accessoire niet in de catalogus zit.
     * @throws AccessoireAlreadyLinkedException     wanneer de accessoire al aan deze groep is gekoppeld.
     */
    public function link(string $groupName, string $accessoireItemcode): void;

    /**
     * @throws GroupNotFoundException wanneer de groep niet bestaat.
     *
     * @return list<Accessoire>
     */
    public function findAllForGroup(string $groupName): array;
}
