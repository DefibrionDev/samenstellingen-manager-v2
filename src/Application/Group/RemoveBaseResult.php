<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class RemoveBaseResult
{
    public function __construct(
        public int $baseId,
        public string $baseName,
        public ?string $familyHeadItemcode,
    ) {
    }
}
