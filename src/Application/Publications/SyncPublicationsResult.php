<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

final readonly class SyncPublicationsResult
{
    /**
     * @param list<PublicationSyncPlan>                              $plans
     * @param list<array{plan: PublicationSyncPlan, error: string}>  $failures
     * @param int                                                    $noopSkipped Aantal itemcodes waar niets aan te zetten viel
     * @param int                                                    $totalCandidates Aantal itemcodes dat zou geplanned worden zonder no-op skip
     * @param list<OnlineNotAssignedRow>                             $onlineNotAssigned itemcodes×website die online staan in AFAS maar niet toegekend zijn (alleen gerapporteerd, nooit uitgezet)
     */
    public function __construct(
        public array $plans,
        public int $appliedCount,
        public array $failures,
        public int $noopSkipped = 0,
        public int $totalCandidates = 0,
        public array $onlineNotAssigned = [],
    ) {
    }
}
