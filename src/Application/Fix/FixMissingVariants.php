<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixMissingVariants
{
    public function __construct(
        public bool $apply = false,
        public ?string $familyHeadItemcode = null,
        public ?int $limit = null,
        public bool $skipPrices = false,
    ) {
    }
}
