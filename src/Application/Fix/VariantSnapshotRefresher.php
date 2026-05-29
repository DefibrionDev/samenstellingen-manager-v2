<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

/**
 * Vernieuwt de lokale AFAS-snapshot (artikelen + samenstellingen + prijzen)
 * zodat net-via-POST aangemaakte varianten zichtbaar zijn voor
 * `FixPriceMissingHandler`. De chained-flow in `FixMissingVariantsHandler`
 * roept dit aan tussen variant-POSTs en de prijs-stap.
 */
interface VariantSnapshotRefresher
{
    public function refresh(): void;
}
