<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\BaseItemAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;

final class InMemoryGroupBaseItemRepository implements GroupBaseItemRepository
{
    /** @var array<int, array<string, GroupBaseItem>> baseId → itemcode → item */
    private array $byBaseAndItemcode = [];

    public function __construct(private readonly GroupBaseRepository $baseRepository)
    {
    }

    public function saveForBase(int $baseId, GroupBaseItem $item): void
    {
        $this->assertBaseExists($baseId);

        if (isset($this->byBaseAndItemcode[$baseId][$item->itemcode])) {
            throw BaseItemAlreadyExistsException::forItemcodeInBase($item->itemcode, $baseId);
        }

        $this->byBaseAndItemcode[$baseId][$item->itemcode] = $item;
    }

    public function findAllForBase(int $baseId): array
    {
        $this->assertBaseExists($baseId);

        $items = array_values($this->byBaseAndItemcode[$baseId] ?? []);
        usort($items, static fn (GroupBaseItem $a, GroupBaseItem $b): int => strcmp($a->itemcode, $b->itemcode));

        return $items;
    }

    private function assertBaseExists(int $baseId): void
    {
        if (!$this->baseRepository->findById($baseId) instanceof GroupBase) {
            throw BaseNotFoundException::forId($baseId);
        }
    }
}
