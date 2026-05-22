<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class AddAccessoireToGroup
{
    public function __construct(
        public string $groupName,
        public string $accessoireItemcode,
    ) {
    }
}
