<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

final readonly class FamilyHeadShift
{
    public function __construct(
        public string $oldHead,
        public string $newHead,
        public int $baseCount,
    ) {
    }
}
