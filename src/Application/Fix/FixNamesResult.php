<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixNamesResult
{
    /**
     * @param list<NameFixPlan> $plans
     * @param list<array{plan: NameFixPlan, error: string}> $failures
     */
    public function __construct(
        public array $plans,
        public int $appliedCount,
        public array $failures,
    ) {
    }
}
