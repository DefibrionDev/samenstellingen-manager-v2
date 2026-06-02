<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Bom\Http;

use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use Defibrion\Samenstellingen\Domain\Bom\BomLineReader;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;

/**
 * Leest BOM-regels live uit AFAS via GetConnector `easylinq_stock_item_parts`.
 *
 * Vereist: het veld `Presentatievolgorde` (PrSe) moet in de connector zijn
 * opgenomen. Zonder dat veld kan deze reader geen unieke regel-key opbouwen.
 *
 * Rij-shape:
 *   {item_id, part_item_id, quantity, type_id, composition_type_id, Presentatievolgorde}
 */
final readonly class HttpBomLineReader implements BomLineReader
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function findLinesByBomItemcode(string $bomItemcode): array
    {
        $rows = $this->client->getConnectorAll('easylinq_stock_item_parts', [
            'filterfieldids' => 'part_item_id',
            'filtervalues' => $bomItemcode,
            'operatortypes' => 1,
        ]);

        $lines = [];
        foreach ($rows as $row) {
            $samenstelling = (string) ($row['item_id'] ?? '');
            $part = (string) ($row['part_item_id'] ?? '');
            $vaIt = (string) ($row['type_id'] ?? '');
            $prSeRaw = $row['Presentatievolgorde'] ?? null;
            if ($samenstelling === '' || $part === '' || $vaIt === '' || !is_int($prSeRaw)) {
                continue;
            }
            $lines[] = new BomLine($samenstelling, $part, $vaIt, $prSeRaw);
        }

        usort(
            $lines,
            static fn (BomLine $a, BomLine $b): int => strcmp($a->samenstellingItemcode, $b->samenstellingItemcode),
        );

        return $lines;
    }

    public function findMaxPrSePerSamenstelling(): array
    {
        $rows = $this->client->getConnectorAll('easylinq_stock_item_parts');

        $max = [];
        foreach ($rows as $row) {
            $samenstelling = (string) ($row['item_id'] ?? '');
            $prSeRaw = $row['Presentatievolgorde'] ?? null;
            if ($samenstelling === '' || !is_int($prSeRaw)) {
                continue;
            }
            $cur = $max[$samenstelling] ?? -1;
            if ($prSeRaw > $cur) {
                $max[$samenstelling] = $prSeRaw;
            }
        }

        return $max;
    }
}
