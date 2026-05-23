<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class AddBaseToGroup
{
    public function __construct(
        public string $familyHeadItemcode,
        public string $name,
        public string $languageCode,
    ) {
    }
}
