<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\AuditVariantParent;
use Defibrion\Samenstellingen\Application\Audit\VariantParentAuditHandler;

/**
 * Vult Itemcode_Parent op matched accessoire-variants met de family-head van
 * hun groep, maar alleen waar 't veld leeg is. Afwijkend gevulde rijen worden
 * nooit overschreven — die belanden in `skippedFilled` voor handmatige review.
 */
final readonly class FixVariantParentHandler
{
    public function __construct(
        private VariantParentAuditHandler $audit,
        private ItemcodeParentWriter $writer,
    ) {
    }

    public function __invoke(FixVariantParent $command): FixVariantParentResult
    {
        $plans = [];
        $skipped = [];
        foreach (($this->audit)(new AuditVariantParent()) as $row) {
            if ($row->currentParent === null || $row->currentParent === '') {
                $plans[] = $row;
            } else {
                $skipped[] = $row;
            }
        }

        if (!$command->apply) {
            return new FixVariantParentResult($plans, $skipped, 0, []);
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

        return new FixVariantParentResult($plans, $skipped, $applied, $failures);
    }
}
