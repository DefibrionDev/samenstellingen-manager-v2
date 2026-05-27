<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class PriceDriftRow
{
    /**
     * @param 'toeslag-drift'|'missing'|'inconsistent-staffel' $status
     */
    public function __construct(
        public string $groupName,
        public string $baseAfasItemcode,
        public string $baseName,
        public string $variantAfasItemcode,
        public string $accessoireItemcode,
        public string $accessoireLabel,
        public int $expectedDeltaCents,
        public string $prijslijstId,
        public ?string $prijslijstOmschrijving,
        public ?int $staffelAantal,
        public ?int $basePrijsCents,
        public ?int $variantPrijsCents,
        public ?int $actualDeltaCents,
        public string $status,
    ) {
    }
}
