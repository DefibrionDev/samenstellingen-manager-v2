<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

interface PriceFixWriter
{
    /**
     * Update een bestaande prijs-rij (PUT richting FbSalesPrice).
     *
     * @throws PriceFixFailedException bij netwerk- of AFAS-fouten.
     */
    public function apply(PriceFixPlan $plan): void;

    /**
     * Insert een nieuwe prijs-rij (POST richting FbSalesPrice). PUT faalt met
     * "Prijs niet gevonden" als de rij nog niet bestaat — gebruik POST daarvoor.
     *
     * @throws PriceFixFailedException bij netwerk- of AFAS-fouten.
     */
    public function insert(PriceFixPlan $plan): void;
}
