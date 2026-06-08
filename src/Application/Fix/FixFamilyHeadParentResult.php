<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\FamilyHeadParentDriftRow;

final readonly class FixFamilyHeadParentResult
{
    /**
     * @param list<FamilyHeadParentDriftRow>                            $plans         items die ge-PUT zouden worden (lege current_parent)
     * @param list<FamilyHeadParentDriftRow>                            $skippedFilled items met afwijkende current_parent — nooit overschrijven
     * @param int                                                       $applied       aantal succesvol ge-PUTd (0 in dry-run)
     * @param list<array{plan: FamilyHeadParentDriftRow, error: string}> $failures
     */
    public function __construct(
        public array $plans,
        public array $skippedFilled,
        public int $applied,
        public array $failures,
    ) {
    }
}
