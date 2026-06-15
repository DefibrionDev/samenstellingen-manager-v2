<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\ProductTypeIssueRow;

final readonly class FixVariantProductTypeResult
{
    /**
     * @param list<ProductTypeIssueRow>                              $plans         auto-fixbare varianten
     * @param list<ProductTypeIssueRow>                              $skipped       base-leeg + geblokkeerd (AFAS-invoer nodig)
     * @param list<array{plan: ProductTypeIssueRow, error: string}>  $failures
     */
    public function __construct(
        public array $plans,
        public array $skipped,
        public int $applied,
        public array $failures,
    ) {
    }
}
