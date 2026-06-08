<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\BaseParentDriftRow;

final readonly class FixBaseParentResult
{
    /**
     * @param list<BaseParentDriftRow>                              $plans
     * @param list<BaseParentDriftRow>                              $skippedFilled
     * @param list<array{plan: BaseParentDriftRow, error: string}>   $failures
     */
    public function __construct(
        public array $plans,
        public array $skippedFilled,
        public int $applied,
        public array $failures,
    ) {
    }
}
