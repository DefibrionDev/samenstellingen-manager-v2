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
     * Vraag de van-referentie-te-spiegelen velden van de referentie-variant op:
     * - `grp` = Artikelgroep
     * - `cbsCode` = CBS-goederencode
     * - `productType` = "Product type (#01)" / Producttype (`U5C3C…`)
     * - `subcategorie` = "Product type (#02)" / Subcategorie (`U79C8…`)
     * - `merknaam` = Merknaam (`UE10D…`)
     *
     * Lege strings voor velden die de referentie zelf leeg heeft — de payload-
     * builder slaat die dan over zodat AFAS niet onnodig wordt overschreven.
     *
     * @return array{grp: string, cbsCode: string, productType: string, subcategorie: string, merknaam: string}
     *
     * @throws VariantWriteContextNotFoundException als het referentie-itemcode niet bestaat.
     */
    public function lookupReferenceFields(string $referenceItemcode): array;

    /**
     * VaIt-waarde voor een BOM-itemcode: `'Sam'` als het zelf een samenstelling
     * is (type_id=7), anders `'Art'` (gewoon artikel).
     */
    public function lookupBomItemType(string $itemcode): string;
}
