<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final readonly class ShowGroupHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private GroupBaseRepository $baseRepository,
        private GroupAccessoireRepository $linkRepository,
    ) {
    }

    public function __invoke(ShowGroup $query): GroupOverview
    {
        $group = $this->groupRepository->findByName($query->name);
        if ($group === null) {
            throw GroupNotFoundException::forName($query->name);
        }

        return new GroupOverview(
            $group,
            $this->baseRepository->findAllForGroup($query->name),
            $this->linkRepository->findAllForGroup($query->name),
        );
    }
}
