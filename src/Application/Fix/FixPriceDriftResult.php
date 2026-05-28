<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixPriceDriftResult
{
    /**
     * @param list<PriceFixPlan> $plans
     * @param list<array{plan: PriceFixPlan, error: string}> $failures
     */
    public function __construct(
        public array $plans,
        public int $appliedCount,
        public array $failures,
    ) {
    }
}
