<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

final class InMemoryGroupVariantRepository implements GroupVariantRepository
{
    private int $nextId = 1;

    /**
     * Per family-head itemcode: key = "baseId|accessoireItemcodeOrEmpty", value = GroupVariant.
     *
     * @var array<string, array<string, GroupVariant>>
     */
    private array $variantsByGroup = [];

    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupBaseRepository $baseRepository,
        private readonly GroupAccessoireRepository $accessoireRepository,
    ) {
    }

    public function regenerateForGroup(string $familyHeadItemcode): void
    {
        $this->assertGroupExists($familyHeadItemcode);

        $bases = $this->baseRepository->findAllForGroup($familyHeadItemcode);
        $accessoires = $this->accessoireRepository->findAllForGroup($familyHeadItemcode);

        $existing = $this->variantsByGroup[$familyHeadItemcode] ?? [];
        $expectedKeys = [];

        foreach ($bases as $base) {
            if ($base->id === null) {
                continue;
            }

            $baseOnlyKey = $this->keyFor($base->id, null);
            $expectedKeys[$baseOnlyKey] = true;
            if (!isset($existing[$baseOnlyKey])) {
                $existing[$baseOnlyKey] = new GroupVariant(
                    $this->nextId++,
                    $base->id,
                    $base->name,
                    null,
                    null,
                    null,
                );
            }

            foreach ($accessoires as $accessoire) {
                $key = $this->keyFor($base->id, $accessoire->itemcode);
                $expectedKeys[$key] = true;
                if (!isset($existing[$key])) {
                    $existing[$key] = new GroupVariant(
                        $this->nextId++,
                        $base->id,
                        $base->name,
                        $accessoire->itemcode,
                        $accessoire->label,
                        null,
                    );
                }
            }
        }

        foreach (array_keys($existing) as $key) {
            if (!isset($expectedKeys[$key])) {
                unset($existing[$key]);
            }
        }

        $this->variantsByGroup[$familyHeadItemcode] = $existing;
    }

    public function findAllForGroup(string $familyHeadItemcode): array
    {
        $this->assertGroupExists($familyHeadItemcode);

        $variants = array_values($this->variantsByGroup[$familyHeadItemcode] ?? []);
        usort($variants, static function (GroupVariant $a, GroupVariant $b): int {
            $byBase = $a->baseId <=> $b->baseId;
            if ($byBase !== 0) {
                return $byBase;
            }
            if ($a->accessoireItemcode === null) {
                return $b->accessoireItemcode === null ? 0 : -1;
            }
            if ($b->accessoireItemcode === null) {
                return 1;
            }

            return strcmp($a->accessoireItemcode, $b->accessoireItemcode);
        });

        return $variants;
    }

    public function markMatched(int $variantId, string $afasItemcode): void
    {
        $this->updateById(
            $variantId,
            static fn (GroupVariant $v): GroupVariant => new GroupVariant(
                $v->id,
                $v->baseId,
                $v->baseName,
                $v->accessoireItemcode,
                $v->accessoireLabel,
                $afasItemcode,
                'matched',
            ),
        );
    }

    public function markNoMatch(int $variantId): void
    {
        $this->updateById(
            $variantId,
            static fn (GroupVariant $v): GroupVariant => new GroupVariant(
                $v->id,
                $v->baseId,
                $v->baseName,
                $v->accessoireItemcode,
                $v->accessoireLabel,
                null,
                'no_match',
            ),
        );
    }

    /**
     * @param callable(GroupVariant): GroupVariant $mutator
     */
    private function updateById(int $variantId, callable $mutator): void
    {
        foreach ($this->variantsByGroup as $familyHead => $variants) {
            foreach ($variants as $key => $variant) {
                if ($variant->id === $variantId) {
                    $this->variantsByGroup[$familyHead][$key] = $mutator($variant);

                    return;
                }
            }
        }
    }

    private function keyFor(int $baseId, ?string $accessoireItemcode): string
    {
        return $baseId . '|' . ($accessoireItemcode ?? '');
    }

    private function assertGroupExists(string $familyHeadItemcode): void
    {
        if (!$this->groupRepository->findByFamilyHeadItemcode($familyHeadItemcode) instanceof Group) {
            throw GroupNotFoundException::forFamilyHeadItemcode($familyHeadItemcode);
        }
    }
}
