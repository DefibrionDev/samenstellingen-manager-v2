<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

/**
 * Eén managed base-samenstelling die ontbreekt in een whitelist-prijslijst:
 * er bestaat geen prijslijst-prijs (debiteur_id IS NULL) voor `(baseAfasItemcode,
 * prijslijstId)`. Read-only signaal — de prijs moet AFAS-zijdig aangemaakt worden.
 */
final readonly class BasePriceGapRow
{
    public function __construct(
        public string $prijslijstId,
        public ?string $prijslijstOmschrijving,
        public string $baseAfasItemcode,
        public string $groupName,
        public string $baseName,
    ) {
    }
}
