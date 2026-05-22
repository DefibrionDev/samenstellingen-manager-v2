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
        $base = new GroupBase($command->itemcode, $command->languageCode, $command->name);
        $this->repository->saveForGroup($command->groupName, $base);
        $this->variantRepository->regenerateForGroup($command->groupName);

        return $base;
    }
}
