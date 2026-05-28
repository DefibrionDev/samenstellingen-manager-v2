<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
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

    public function findAll(): array
    {
        $groups = array_values($this->byName);
        usort($groups, static fn (Group $a, Group $b): int => strcmp($a->name, $b->name));

        return $groups;
    }

    public function delete(string $familyHeadItemcode): void
    {
        $group = $this->byFamilyHead[$familyHeadItemcode] ?? null;
        if ($group === null) {
            return; // idempotent — onbekende family-head is no-op
        }
        unset($this->byFamilyHead[$familyHeadItemcode], $this->byName[$group->name]);
    }

    public function updateModelNaam(string $familyHeadItemcode, string $taal, ?string $naam): void
    {
        $existing = $this->byFamilyHead[$familyHeadItemcode] ?? null;
        if ($existing === null) {
            throw GroupNotFoundException::forFamilyHeadItemcode($familyHeadItemcode);
        }
        $clean = $naam !== null && trim($naam) !== '' ? trim($naam) : null;
        $updated = new Group(
            $existing->name,
            $existing->familyHeadItemcode,
            $taal === 'nl' ? $clean : $existing->modelNameNl,
            $taal === 'fr' ? $clean : $existing->modelNameFr,
            $taal === 'en' ? $clean : $existing->modelNameEn,
        );
        $this->byFamilyHead[$familyHeadItemcode] = $updated;
        $this->byName[$existing->name] = $updated;
    }
}
