<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasSamenstellingenFetcher
{
    /**
     * Haal álle samenstellingen (type_id=7) uit AFAS, inclusief hun BOM en Itemcode_Parent.
     *
     * Geen pre-filtering op family-head: matching gebeurt puur op BOM-content.
     * Of een AFAS-samenstelling de juiste Itemcode_Parent heeft staan is een
     * aparte audit, geen voorwaarde voor het matchen.
     *
     * @return list<AfasSamenstelling>
     */
    public function fetchAll(): array;
}
