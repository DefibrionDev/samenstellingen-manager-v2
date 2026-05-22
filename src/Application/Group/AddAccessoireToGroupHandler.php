<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

final readonly class AddAccessoireToGroupHandler
{
    public function __construct(
        private GroupAccessoireRepository $repository,
        private GroupVariantRepository $variantRepository,
    ) {
    }

    public function __invoke(AddAccessoireToGroup $command): void
    {
        $this->repository->link($command->groupName, $command->accessoireItemcode);
        $this->variantRepository->regenerateForGroup($command->groupName);
    }
}
