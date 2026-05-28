<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

interface PriceFixWriter
{
    /**
     * Pas de prijs-correctie toe (PUT richting FbSalesPrice voor een bestaande rij).
     *
     * @throws PriceFixFailedException bij netwerk- of AFAS-fouten.
     */
    public function apply(PriceFixPlan $plan): void;
}
