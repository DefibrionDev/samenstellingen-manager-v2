<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final class InMemoryGroupRepository implements GroupRepository
{
    /** @var array<string, Group> */
    private array $byName = [];
    /** @var array<string, Group> */
    private array $byFamilyHead = [];

    public function save(Group $group): void
    {
        if (isset($this->byName[$group->name])) {
            throw GroupAlreadyExistsException::forName($group->name);
        }
        if (isset($this->byFamilyHead[$group->familyHeadItemcode])) {
            throw GroupAlreadyExistsException::forFamilyHeadItemcode($group->familyHeadItemcode);
        }

        $this->byName[$group->name] = $group;
        $this->byFamilyHead[$group->familyHeadItemcode] = $group;
    }

    public function findByName(string $name): ?Group
    {
        return $this->byName[$name] ?? null;
    }

    public function findByFamilyHeadItemcode(string $familyHeadItemcode): ?Group
    {
        return $this->byFamilyHead[$familyHeadItemcode] ?? null;
    }
}
