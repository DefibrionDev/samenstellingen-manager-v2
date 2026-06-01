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

        // Itemcode is leidend wanneer aanwezig. Bases zonder SKU mogen
        // dezelfde naam delen — naam-UNIQUE is per slice 41 verwijderd.
        if ($base->afasItemcode !== null) {
            foreach ($this->byFamilyHead[$familyHeadItemcode] ?? [] as $existingId) {
                $existing = $this->byId[$existingId] ?? null;
                if ($existing !== null && $existing->afasItemcode === $base->afasItemcode) {
                    throw BaseAlreadyExistsException::forItemcodeInGroup($base->afasItemcode, $familyHeadItemcode);
                }
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

    public function findAllByAfasItemcode(string $afasItemcode): array
    {
        $result = [];
        foreach ($this->byId as $base) {
            if ($base->afasItemcode === $afasItemcode) {
                $result[] = $base;
            }
        }

        return $result;
    }

    public function findByAfasItemcodeInGroup(string $familyHeadItemcode, string $afasItemcode): ?GroupBase
    {
        $this->assertGroupExists($familyHeadItemcode);

        foreach ($this->byFamilyHead[$familyHeadItemcode] ?? [] as $baseId) {
            $base = $this->byId[$baseId] ?? null;
            if ($base !== null && $base->afasItemcode === $afasItemcode) {
                return $base;
            }
        }

        return null;
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

    public function delete(int $baseId): void
    {
        $base = $this->byId[$baseId] ?? null;
        if ($base === null) {
            return;
        }
        unset($this->byId[$baseId]);
        foreach ($this->byFamilyHead as $familyHead => $ids) {
            $this->byFamilyHead[$familyHead] = array_values(array_filter($ids, static fn (int $id) => $id !== $baseId));
        }
    }

    public function findFamilyHeadForBase(int $baseId): ?string
    {
        foreach ($this->byFamilyHead as $familyHead => $ids) {
            if (in_array($baseId, $ids, true)) {
                return (string) $familyHead;
            }
        }

        return null;
    }

    public function setVariantLabelByAfasItemcode(string $afasItemcode, ?string $variantLabel): int
    {
        $normalized = $variantLabel !== null ? trim($variantLabel) : null;
        if ($normalized === '') {
            $normalized = null;
        }

        $count = 0;
        foreach ($this->byId as $id => $base) {
            if ($base->afasItemcode !== $afasItemcode) {
                continue;
            }
            $this->byId[$id] = new GroupBase(
                $base->id,
                $base->name,
                $base->languageCode,
                $base->afasItemcode,
                $normalized,
            );
            ++$count;
        }

        return $count;
    }

    public function setLanguageCodeByAfasItemcode(string $afasItemcode, string $languageCode): int
    {
        $normalized = trim($languageCode);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Taal-code mag niet leeg zijn.');
        }

        $count = 0;
        foreach ($this->byId as $id => $base) {
            if ($base->afasItemcode !== $afasItemcode) {
                continue;
            }
            $this->byId[$id] = new GroupBase(
                $base->id,
                $base->name,
                $normalized,
                $base->afasItemcode,
                $base->variantLabel,
            );
            ++$count;
        }

        return $count;
    }

    public function renameFromAfas(array $afasNameByItemcode): int
    {
        // PHP cast numerieke string-keys naar int — normaliseer terug naar string-lookup.
        $byString = [];
        foreach ($afasNameByItemcode as $code => $name) {
            $byString[(string) $code] = $name;
        }

        $count = 0;
        foreach ($this->byId as $id => $base) {
            if ($base->afasItemcode === null) {
                continue;
            }
            $newName = $byString[$base->afasItemcode] ?? null;
            if ($newName === null || $newName === $base->name) {
                continue;
            }
            $this->byId[$id] = new GroupBase(
                $base->id,
                $newName,
                $base->languageCode,
                $base->afasItemcode,
                $base->variantLabel,
            );
            ++$count;
        }

        return $count;
    }

    public function findAllAfasItemcodes(): array
    {
        $codes = [];
        foreach ($this->byId as $base) {
            if ($base->afasItemcode !== null) {
                $codes[] = $base->afasItemcode;
            }
        }

        return array_values(array_unique($codes));
    }

    private function assertGroupExists(string $familyHeadItemcode): void
    {
        if (!$this->groupRepository->findByFamilyHeadItemcode($familyHeadItemcode) instanceof Group) {
            throw GroupNotFoundException::forFamilyHeadItemcode($familyHeadItemcode);
        }
    }
}
