<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\VariantParentDriftRow;

final readonly class FixVariantParentResult
{
    /**
     * @param list<VariantParentDriftRow>                              $plans
     * @param list<VariantParentDriftRow>                              $skippedFilled
     * @param list<array{plan: VariantParentDriftRow, error: string}>   $failures
     */
    public function __construct(
        public array $plans,
        public array $skippedFilled,
        public int $applied,
        public array $failures,
    ) {
    }
}
