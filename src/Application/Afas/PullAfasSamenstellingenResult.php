<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

use Defibrion\Samenstellingen\Application\Group\SyncAllSummary;

final readonly class PullAfasSamenstellingenResult
{
    public function __construct(
        public int $samenstellingen,
        public int $articles,
        public SyncAllSummary $sync,
        public int $prijzen = 0,
        public int $prijslijsten = 0,
        public int $familyHeadShiftsApplied = 0,
        public int $basesRenamed = 0,
    ) {
    }
}
