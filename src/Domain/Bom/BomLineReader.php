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
}
