<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

final readonly class SyncPublicationsResult
{
    /**
     * @param list<PublicationSyncPlan>                              $plans
     * @param list<array{plan: PublicationSyncPlan, error: string}>  $failures
     */
    public function __construct(
        public array $plans,
        public int $appliedCount,
        public array $failures,
    ) {
    }
}
