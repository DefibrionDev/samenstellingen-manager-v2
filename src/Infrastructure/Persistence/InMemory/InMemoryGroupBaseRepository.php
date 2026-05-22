<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final class InMemoryGroupBaseRepository implements GroupBaseRepository
{
    /** @var array<string, array<string, GroupBase>> */
    private array $byGroupAndItemcode = [];

    public function __construct(private readonly GroupRepository $groupRepository)
    {
    }

    public function saveForGroup(string $groupName, GroupBase $base): void
    {
        $this->assertGroupExists($groupName);

        if (isset($this->byGroupAndItemcode[$groupName][$base->itemcode])) {
            throw BaseAlreadyExistsException::forItemcodeInGroup($base->itemcode, $groupName);
        }

        $this->byGroupAndItemcode[$groupName][$base->itemcode] = $base;
    }

    public function findAllForGroup(string $groupName): array
    {
        $this->assertGroupExists($groupName);

        return array_values($this->byGroupAndItemcode[$groupName] ?? []);
    }

    private function assertGroupExists(string $groupName): void
    {
        if ($this->groupRepository->findByName($groupName) === null) {
            throw GroupNotFoundException::forName($groupName);
        }
    }
}
