<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasPrijzenFetcher
{
    /**
     * Haal alle actieve prijs-rijen op (Einddatum leeg of ≥ vandaag).
     *
     * @return list<AfasPrijs>
     */
    public function fetchActive(): array;
}
