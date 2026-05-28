<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

interface BeginDateLookup
{
    /**
     * Vraag de echte AFAS-begindatum (YYYY-MM-DD) op voor een (itemcode,
     * prijslijst, staffel)-combinatie. Easylinq's `date` is een per-dag-view
     * en bevat NIET de echte begindatum die FbSalesPrice nodig heeft voor PUT.
     */
    public function find(string $itemcode, string $prijslijstId, ?int $staffelAantal): ?string;
}
