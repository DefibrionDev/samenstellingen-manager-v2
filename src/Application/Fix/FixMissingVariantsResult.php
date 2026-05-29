<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

/**
 * @phpstan-type Failure array{plan: VariantFixMissingPlan, error: string}
 * @phpstan-type Skip array{itemcode: string, reason: string}
 */
final readonly class FixMissingVariantsResult
{
    /**
     * @param list<VariantFixMissingPlan>                    $plans
     * @param list<array{plan: VariantFixMissingPlan, error: string}> $failures
     * @param list<array{itemcode: string, reason: string}>  $skipped
     */
    public function __construct(
        public array $plans,
        public int $appliedCount,
        public array $failures,
        public array $skipped,
    ) {
    }
}
