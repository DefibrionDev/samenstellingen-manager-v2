<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\AuditBaseParent;
use Defibrion\Samenstellingen\Application\Audit\BaseParentAuditHandler;

/**
 * Vult Itemcode_Parent op non-head bases met de family-head, maar alleen
 * waar 't veld leeg is. Afwijkend gevulde rijen worden nooit overschreven —
 * die belanden in `skippedFilled` voor handmatige review.
 */
final readonly class FixBaseParentHandler
{
    public function __construct(
        private BaseParentAuditHandler $audit,
        private ItemcodeParentWriter $writer,
    ) {
    }

    public function __invoke(FixBaseParent $command): FixBaseParentResult
    {
        $plans = [];
        $skipped = [];
        foreach (($this->audit)(new AuditBaseParent()) as $row) {
            if ($row->currentParent === null || $row->currentParent === '') {
                $plans[] = $row;
            } else {
                $skipped[] = $row;
            }
        }

        if (!$command->apply) {
            return new FixBaseParentResult($plans, $skipped, 0, []);
        }

        $applied = 0;
        $failures = [];
        foreach ($plans as $plan) {
            try {
                $this->writer->write($plan->afasItemcode, $plan->expectedParent);
                ++$applied;
            } catch (ItemcodeParentWriteFailedException $e) {
                $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
            }
        }

        return new FixBaseParentResult($plans, $skipped, $applied, $failures);
    }
}
