<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

/**
 * Eén itemcode × website dat in AFAS online staat (Sync of Tonen = true) terwijl
 * de tool die website niet heeft toegekend. Wordt NIET gemuteerd door de sync —
 * alleen gerapporteerd (zie de "online maar niet toegekend"-audit, PLAN.md §12).
 */
final readonly class OnlineNotAssignedRow
{
    public function __construct(
        public string $afasItemcode,
        public string $baseAfasItemcode,
        public string $websiteName,
    ) {
    }
}
