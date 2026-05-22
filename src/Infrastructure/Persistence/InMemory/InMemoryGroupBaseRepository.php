<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final class InMemoryGroupBaseRepository implements GroupBaseRepository
{
    private int $nextId = 1;

    /** @var array<int, GroupBase> */
    private array $byId = [];

    /** @var array<string, list<int>> family-head itemcode → list of base ids */
    private array $byFamilyHead = [];

    public function __construct(private readonly GroupRepository $groupRepository)
    {
    }

    public function saveForGroup(string $familyHeadItemcode, GroupBase $base): GroupBase
    {
        $this->assertGroupExists($familyHeadItemcode);

        foreach ($this->byFamilyHead[$familyHeadItemcode] ?? [] as $existingId) {
            if (($this->byId[$existingId] ?? null)?->name === $base->name) {
                throw BaseAlreadyExistsException::forNameInGroup($base->name, $familyHeadItemcode);
            }
        }

        $persisted = $base->withId($this->nextId);
        ++$this->nextId;
        $this->byId[$persisted->id ?? 0] = $persisted;
        $this->byFamilyHead[$familyHeadItemcode][] = $persisted->id ?? 0;

        return $persisted;
    }

    public function findById(int $baseId): ?GroupBase
    {
        return $this->byId[$baseId] ?? null;
    }

    public function findAllForGroup(string $familyHeadItemcode): array
    {
        $this->assertGroupExists($familyHeadItemcode);

        $bases = [];
        foreach ($this->byFamilyHead[$familyHeadItemcode] ?? [] as $baseId) {
            $base = $this->byId[$baseId] ?? null;
            if ($base !== null) {
                $bases[] = $base;
            }
        }

        usort($bases, static fn (GroupBase $a, GroupBase $b): int => ($a->id ?? 0) <=> ($b->id ?? 0));

        return $bases;
    }

    private function assertGroupExists(string $familyHeadItemcode): void
    {
        if (!$this->groupRepository->findByFamilyHeadItemcode($familyHeadItemcode) instanceof Group) {
            throw GroupNotFoundException::forFamilyHeadItemcode($familyHeadItemcode);
        }
    }
}
