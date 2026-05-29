<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantSnapshotRefresher;

/**
 * No-op variant — voor tests waar InMemory-repositories de net-aangemaakte
 * varianten al expliciet beschikbaar maken (geen "snapshot" om te vernieuwen).
 */
final class NullVariantSnapshotRefresher implements VariantSnapshotRefresher
{
    public int $callCount = 0;

    public function refresh(): void
    {
        $this->callCount++;
    }
}
