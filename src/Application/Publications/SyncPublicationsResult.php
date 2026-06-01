<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

final readonly class SyncPublicationsResult
{
    /**
     * @param list<PublicationSyncPlan>                              $plans
     * @param list<array{plan: PublicationSyncPlan, error: string}>  $failures
     * @param int                                                    $noopSkipped Aantal itemcodes waar AFAS-state al match
     * @param int                                                    $totalCandidates Aantal itemcodes dat zou geplanned worden zonder no-op skip
     */
    public function __construct(
        public array $plans,
        public int $appliedCount,
        public array $failures,
        public int $noopSkipped = 0,
        public int $totalCandidates = 0,
    ) {
    }
}
