<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class FamilyHeadParentDriftRow
{
    public function __construct(
        public string $familyHead,
        public ?string $currentParent,
        public string $expectedParent,
        public string $groupName,
    ) {
    }
}
