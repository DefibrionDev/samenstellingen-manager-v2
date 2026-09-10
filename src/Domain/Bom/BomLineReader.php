<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Bom;

/**
 * Zoek alle BOM-regels in AFAS waarin een specifiek onderdeel voorkomt.
 * Gebruikt door `bom:strip-component` om per samenstelling de juiste
 * (PrSe, VaIt) te achterhalen voor de FbCompositionPart-delete.
 */
interface BomLineReader
{
    /**
     * @return list<BomLine>
     */
    public function findLinesByBomItemcode(string $bomItemcode): array;

    /**
     * Bouwt een map samenstelling-itemcode → hoogste PrSe in z'n BOM. Gebruikt
     * door restore-flows om een vrije PrSe te kiezen voor de nieuwe regel
     * (`max + 10`). Eenmalige bulk-pull voorkomt N HTTP-calls.
     *
     * @return array<string, int>
     */
    public function findMaxPrSePerSamenstelling(): array;

    /**
     * Alle BOM-regels (één bulk-pull). Gebruikt door `bom:strip-component` om
     * te controleren of de PrSe van een te-strippen regel uniek is binnen
     * z'n samenstelling: AFAS matcht de FbCompositionPart-delete op PrSe
     * alléén, dus bij een gedeelde PrSe kan de verkeerde regel verdwijnen.
     *
     * @return list<BomLine>
     */
    public function findAllLines(): array;
}
