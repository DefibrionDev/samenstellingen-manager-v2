<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

final class InMemoryGroupVariantRepository implements GroupVariantRepository
{
    /**
     * Per groep een dictionary: key = "baseItemcode|accessoireItemcodeOrNull", value = GroupVariant.
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

    public function regenerateForGroup(string $groupName): void
    {
        $this->assertGroupExists($groupName);

        $bases = $this->baseRepository->findAllForGroup($groupName);
        $accessoires = $this->accessoireRepository->findAllForGroup($groupName);

        $existing = $this->variantsByGroup[$groupName] ?? [];
        $expectedKeys = [];

        foreach ($bases as $base) {
            $baseOnlyKey = $this->keyFor($base->itemcode, null);
            $expectedKeys[$baseOnlyKey] = true;
            if (!isset($existing[$baseOnlyKey])) {
                $existing[$baseOnlyKey] = new GroupVariant(
                    $base->itemcode,
                    $base->languageCode,
                    $base->name,
                    null,
                    null,
                    null,
                );
            }

            foreach ($accessoires as $accessoire) {
                $key = $this->keyFor($base->itemcode, $accessoire->itemcode);
                $expectedKeys[$key] = true;
                if (!isset($existing[$key])) {
                    $existing[$key] = new GroupVariant(
                        $base->itemcode,
                        $base->languageCode,
                        $base->name,
                        $accessoire->itemcode,
                        $accessoire->label,
                        null,
                    );
                }
            }
        }

        foreach (array_keys($existing) as $key) {
            $key = (string) $key;
            if (!isset($expectedKeys[$key])) {
                unset($existing[$key]);
            }
        }

        $this->variantsByGroup[$groupName] = $existing;
    }

    public function findAllForGroup(string $groupName): array
    {
        $this->assertGroupExists($groupName);

        $variants = array_values($this->variantsByGroup[$groupName] ?? []);
        usort($variants, static function (GroupVariant $a, GroupVariant $b): int {
            $byBase = strcmp($a->baseItemcode, $b->baseItemcode);
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

    private function keyFor(string $baseItemcode, ?string $accessoireItemcode): string
    {
        return $baseItemcode . '|' . ($accessoireItemcode ?? '');
    }

    private function assertGroupExists(string $groupName): void
    {
        if ($this->groupRepository->findByName($groupName) === null) {
            throw GroupNotFoundException::forName($groupName);
        }
    }
}
