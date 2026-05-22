<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final readonly class CreateGroupHandler
{
    public function __construct(private GroupRepository $repository)
    {
    }

    public function __invoke(CreateGroup $command): Group
    {
        $group = new Group($command->name, $command->familyHeadItemcode);
        $this->repository->save($group);

        return $group;
    }
}
