<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class BaseParentDriftRow
{
    public function __construct(
        public string $afasItemcode,
        public ?string $currentParent,
        public string $expectedParent,
        public string $groupName,
        public string $languageCode,
    ) {
    }
}
