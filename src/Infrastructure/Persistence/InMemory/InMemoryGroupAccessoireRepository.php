<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final class InMemoryGroupAccessoireRepository implements GroupAccessoireRepository
{
    /** @var array<string, array<string, true>> family-head itemcode → set of accessoire itemcodes */
    private array $linksByGroup = [];

    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly AccessoireRepository $accessoireRepository,
    ) {
    }

    public function link(string $familyHeadItemcode, string $accessoireItemcode): void
    {
        $this->assertGroupExists($familyHeadItemcode);

        if ($this->accessoireRepository->findByItemcode($accessoireItemcode) === null) {
            throw AccessoireNotFoundException::forItemcode($accessoireItemcode);
        }

        if (isset($this->linksByGroup[$familyHeadItemcode][$accessoireItemcode])) {
            throw AccessoireAlreadyLinkedException::forAccessoireInGroup(
                $accessoireItemcode,
                $familyHeadItemcode,
            );
        }

        $this->linksByGroup[$familyHeadItemcode][$accessoireItemcode] = true;
    }

    public function findAllForGroup(string $familyHeadItemcode): array
    {
        $this->assertGroupExists($familyHeadItemcode);

        $itemcodes = array_keys($this->linksByGroup[$familyHeadItemcode] ?? []);
        $accessoires = [];
        foreach ($itemcodes as $itemcode) {
            $accessoire = $this->accessoireRepository->findByItemcode((string) $itemcode);
            if ($accessoire instanceof Accessoire) {
                $accessoires[] = $accessoire;
            }
        }

        return $accessoires;
    }

    private function assertGroupExists(string $familyHeadItemcode): void
    {
        if (!$this->groupRepository->findByFamilyHeadItemcode($familyHeadItemcode) instanceof Group) {
            throw GroupNotFoundException::forFamilyHeadItemcode($familyHeadItemcode);
        }
    }
}
