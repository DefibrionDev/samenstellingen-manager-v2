<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class CreateGroup
{
    public function __construct(
        public string $name,
        public string $familyHeadItemcode,
    ) {
    }
}
