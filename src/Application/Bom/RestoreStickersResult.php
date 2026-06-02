<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

final readonly class RestoreStickersResult
{
    /**
     * @param list<array{baseId: int, baseAfasItemcode: ?string, languageCode: string, sticker: string}>  $toolInserts
     * @param list<BomComponentRestorePlan>                                                              $afasPlans
     * @param list<array{plan: BomComponentRestorePlan, error: string}>                                  $failures
     */
    public function __construct(
        public array $toolInserts,
        public array $afasPlans,
        public int $toolInsertedCount,
        public int $afasAppliedCount,
        public array $failures,
    ) {
    }
}
