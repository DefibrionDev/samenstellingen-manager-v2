<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

interface GroupBaseItemRepository
{
    /**
     * @throws BaseNotFoundException          wanneer de base niet bestaat.
     * @throws BaseItemAlreadyExistsException wanneer een item met deze itemcode al bij deze base zit.
     */
    public function saveForBase(int $baseId, GroupBaseItem $item): void;

    /**
     * @throws BaseNotFoundException wanneer de base niet bestaat.
     *
     * @return list<GroupBaseItem>
     */
    public function findAllForBase(int $baseId): array;
}
