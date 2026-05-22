<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final class InMemoryGroupAccessoireRepository implements GroupAccessoireRepository
{
    /** @var array<string, array<string, true>> */
    private array $linksByGroup = [];

    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly AccessoireRepository $accessoireRepository,
    ) {
    }

    public function link(string $groupName, string $accessoireItemcode): void
    {
        $this->assertGroupExists($groupName);

        if ($this->accessoireRepository->findByItemcode($accessoireItemcode) === null) {
            throw AccessoireNotFoundException::forItemcode($accessoireItemcode);
        }

        if (isset($this->linksByGroup[$groupName][$accessoireItemcode])) {
            throw AccessoireAlreadyLinkedException::forAccessoireInGroup($accessoireItemcode, $groupName);
        }

        $this->linksByGroup[$groupName][$accessoireItemcode] = true;
    }

    public function findAllForGroup(string $groupName): array
    {
        $this->assertGroupExists($groupName);

        $itemcodes = array_keys($this->linksByGroup[$groupName] ?? []);
        $accessoires = [];
        foreach ($itemcodes as $itemcode) {
            $accessoire = $this->accessoireRepository->findByItemcode((string) $itemcode);
            if ($accessoire instanceof Accessoire) {
                $accessoires[] = $accessoire;
            }
        }

        return $accessoires;
    }

    private function assertGroupExists(string $groupName): void
    {
        if ($this->groupRepository->findByName($groupName) === null) {
            throw GroupNotFoundException::forName($groupName);
        }
    }
}
