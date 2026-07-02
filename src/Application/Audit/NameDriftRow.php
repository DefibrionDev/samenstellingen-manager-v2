<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class NameDriftRow
{
    public function __construct(
        public string $afasItemcode,
        public string $groupName,
        public string $familyHead,
        public string $baseName,
        public ?string $baseItemcode,
        public string $languageCode,
        public ?string $accessoireItemcode,
        public ?string $accessoireLabel,
        public string $expected,
        public string $actual,
    ) {
    }
}
