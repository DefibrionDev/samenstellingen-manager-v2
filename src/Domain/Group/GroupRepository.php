<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupRepository
{
    /**
     * @throws GroupAlreadyExistsException als er al een groep met deze naam bestaat.
     */
    public function save(Group $group): void;

    public function findByName(string $name): ?Group;
}
