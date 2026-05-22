<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

final readonly class AddBaseToGroupHandler
{
    public function __construct(
        private GroupBaseRepository $repository,
        private GroupVariantRepository $variantRepository,
    ) {
    }

    public function __invoke(AddBaseToGroup $command): GroupBase
    {
        $persisted = $this->repository->saveForGroup(
            $command->familyHeadItemcode,
            new GroupBase(null, $command->name),
        );
        $this->variantRepository->regenerateForGroup($command->familyHeadItemcode);

        return $persisted;
    }
}
