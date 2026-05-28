<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixPriceMissingResult
{
    /**
     * @param list<PriceFixPlan> $plans
     * @param list<array{plan: PriceFixPlan, error: string}> $failures
     * @param list<string> $skippedNoArticle Variants die niet als artikel in AFAS bestaan
     */
    public function __construct(
        public array $plans,
        public int $appliedCount,
        public array $failures,
        public array $skippedNoArticle,
    ) {
    }
}
