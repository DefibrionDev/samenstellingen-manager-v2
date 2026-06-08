<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\AuditFamilyHeadParent;
use Defibrion\Samenstellingen\Application\Audit\FamilyHeadParentAuditHandler;

/**
 * Vult family-head's eigen `Itemcode_Parent` in AFAS met self, maar alleen
 * waar 't veld leeg is. Afwijkend gevulde rijen (head wijst naar iets anders
 * dan zichzelf) worden NOOIT overschreven — die belanden in `skippedFilled`
 * zodat de gebruiker ze handmatig kan onderzoeken.
 */
final readonly class FixFamilyHeadParentHandler
{
    public function __construct(
        private FamilyHeadParentAuditHandler $audit,
        private FamilyHeadParentWriter $writer,
    ) {
    }

    public function __invoke(FixFamilyHeadParent $command): FixFamilyHeadParentResult
    {
        $plans = [];
        $skipped = [];
        foreach (($this->audit)(new AuditFamilyHeadParent()) as $row) {
            if ($row->currentParent === null || $row->currentParent === '') {
                $plans[] = $row;
            } else {
                $skipped[] = $row;
            }
        }

        if (!$command->apply) {
            return new FixFamilyHeadParentResult($plans, $skipped, 0, []);
        }

        $applied = 0;
        $failures = [];
        foreach ($plans as $plan) {
            try {
                $this->writer->write($plan->familyHead, $plan->expectedParent);
                ++$applied;
            } catch (FamilyHeadParentWriteFailedException $e) {
                $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
            }
        }

        return new FixFamilyHeadParentResult($plans, $skipped, $applied, $failures);
    }
}
