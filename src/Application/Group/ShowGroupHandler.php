<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final readonly class ShowGroupHandler
{
    public function __construct(private GroupRepository $repository)
    {
    }

    public function __invoke(ShowGroup $query): Group
    {
        $group = $this->repository->findByName($query->name);
        if ($group === null) {
            throw GroupNotFoundException::forName($query->name);
        }

        return $group;
    }
}
