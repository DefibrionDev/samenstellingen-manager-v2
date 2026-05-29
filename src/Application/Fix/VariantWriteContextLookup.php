<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

/**
 * Spiegel-data die de HTTP-writer nodig heeft van AFAS om een nieuwe variant
 * aan te kunnen maken: `Grp` / `CsGc` per referentie-variant + `VaIt` per
 * BOM-itemcode. Apart van de writer zodat de HTTP-implementatie de AFAS-pull
 * één keer per run doet en de cache deelt, en de test-implementatie alleen
 * een data-array hoeft te leveren.
 */
interface VariantWriteContextLookup
{
    /**
     * Vraag artikelgroep + CBS-goederencode van de referentie-variant op.
     *
     * @return array{grp: string, cbsCode: string}
     *
     * @throws VariantWriteContextNotFoundException als het referentie-itemcode niet bestaat of niet alle velden heeft.
     */
    public function lookupReferenceFields(string $referenceItemcode): array;

    /**
     * VaIt-waarde voor een BOM-itemcode: `'Sam'` als het zelf een samenstelling
     * is (type_id=7), anders `'Art'` (gewoon artikel).
     */
    public function lookupBomItemType(string $itemcode): string;
}
