<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\AuditProductType;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeAuditHandler;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeIssueType;

/**
 * Trekt accessoire-varianten gelijk aan de producttypes van hun base. Alleen
 * `VariantFixable`-rijen worden geschreven (base gevuld) — leeg én afwijkend,
 * de base is leidend. `BaseEmpty` en `VariantBlocked` belanden in `skipped`
 * en wachten op handmatige AFAS-invoer. Zie PLAN-AFAS.md §35.
 */
final readonly class FixVariantProductTypeHandler
{
    public function __construct(
        private ProductTypeAuditHandler $audit,
        private ProductTypeWriter $writer,
    ) {
    }

    public function __invoke(FixVariantProductType $command): FixVariantProductTypeResult
    {
        $plans = [];
        $skipped = [];
        foreach (($this->audit)(new AuditProductType()) as $row) {
            if ($row->issueType === ProductTypeIssueType::VariantFixable
                && $row->expected01 !== null
                && $row->expected02 !== null) {
                $plans[] = $row;
            } else {
                $skipped[] = $row;
            }
        }

        if (!$command->apply) {
            return new FixVariantProductTypeResult($plans, $skipped, 0, []);
        }

        $applied = 0;
        $failures = [];
        foreach ($plans as $plan) {
            if ($plan->expected01 === null || $plan->expected02 === null) {
                continue; // door de filter al uitgesloten; defensief voor de type-checker
            }
            try {
                $this->writer->write($plan->afasItemcode, $plan->expected01, $plan->expected02);
                ++$applied;
            } catch (ProductTypeWriteFailedException $e) {
                $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
            }
        }

        return new FixVariantProductTypeResult($plans, $skipped, $applied, $failures);
    }
}
