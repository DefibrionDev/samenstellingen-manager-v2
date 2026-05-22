<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;

final readonly class AddBaseItemHandler
{
    public function __construct(private GroupBaseItemRepository $repository)
    {
    }

    public function __invoke(AddBaseItem $command): GroupBaseItem
    {
        $item = new GroupBaseItem($command->itemcode, $command->name);
        $this->repository->saveForBase($command->baseId, $item);

        return $item;
    }
}
