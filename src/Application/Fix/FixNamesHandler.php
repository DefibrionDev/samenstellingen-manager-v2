<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\AuditNames;
use Defibrion\Samenstellingen\Application\Audit\NameAuditHandler;

/**
 * Pakt name-drift (expected != actual) en stuurt elke rij als PUT
 * FbItemArticle.Ds = expected naar AFAS. Dry-run: alleen plannen tonen;
 * apply=true: echt schrijven. Skipt rijen waar expected==actual (no-op).
 * Met baseItemcodes wordt alleen drift van die bases gefixt (gerichte rename
 * zonder de rest van de drift-lijst mee te schrijven).
 */
final readonly class FixNamesHandler
{
    public function __construct(
        private NameAuditHandler $audit,
        private NameFixWriter $writer,
    ) {
    }

    public function __invoke(FixNames $command): FixNamesResult
    {
        $rows = ($this->audit)(new AuditNames());

        $plans = [];
        foreach ($rows as $row) {
            if ($row->expected === $row->actual) {
                continue;
            }
            if ($command->baseItemcodes !== null
                && !in_array($row->baseItemcode, $command->baseItemcodes, true)) {
                continue;
            }
            $plans[] = new NameFixPlan($row->afasItemcode, $row->actual, $row->expected);
            if ($command->limit !== null && count($plans) >= $command->limit) {
                break;
            }
        }

        $applied = 0;
        $failures = [];
        if ($command->apply) {
            foreach ($plans as $plan) {
                try {
                    $this->writer->apply($plan);
                    $applied++;
                } catch (NameFixFailedException $e) {
                    $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
                }
            }
        }

        return new FixNamesResult($plans, $applied, $failures);
    }
}
