<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;

interface GroupAccessoireRepository
{
    /**
     * @throws GroupNotFoundException
     * @throws AccessoireNotFoundException
     * @throws AccessoireAlreadyLinkedException
     */
    public function link(string $familyHeadItemcode, string $accessoireItemcode): void;

    /**
     * @throws GroupNotFoundException
     *
     * @return list<Accessoire>
     */
    public function findAllForGroup(string $familyHeadItemcode): array;
}
