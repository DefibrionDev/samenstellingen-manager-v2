<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\BeginDateLookup;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;

/**
 * Roept `Get_Prijzen` aan om de echte begindatum van een prijs-rij te
 * vinden. Get_Prijzen levert (begin, eind)-ranges; easylinq levert
 * per-dag-rijen zonder echte begindatum.
 *
 * Filter: itemcode + prijslijst + debiteur leeg, pak rij waarvan
 * Einddatum leeg is (actieve range). Bij meerdere actieve rijen: filter
 * op staffel (`Staffelprijs` veld, niet rechtstreeks beschikbaar).
 */
final readonly class HttpGetPrijzenBeginDateLookup implements BeginDateLookup
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function find(string $itemcode, string $prijslijstId, ?int $staffelAantal): ?string
    {
        $rows = $this->client->getConnectorAll('Get_Prijzen', [
            'filterfieldids' => 'Itemcode,Prijslijst',
            'filtervalues' => $itemcode . ',' . $prijslijstId,
            'operatortypes' => '1,1',
        ], 100);

        $candidates = [];
        foreach ($rows as $row) {
            $debtor = isset($row['Debiteur']) && is_string($row['Debiteur']) ? trim($row['Debiteur']) : '';
            if ($debtor !== '') {
                continue;
            }
            $eind = isset($row['Einddatum']) && is_string($row['Einddatum']) ? trim($row['Einddatum']) : '';
            if ($eind !== '') {
                continue; // alleen actieve rijen
            }
            $begin = isset($row['Begindatum']) && is_string($row['Begindatum']) ? substr($row['Begindatum'], 0, 10) : '';
            if ($begin === '') {
                continue;
            }
            $candidates[] = $begin;
        }

        if ($candidates === []) {
            return null;
        }

        // Meest recente begindatum is de geldende rij.
        sort($candidates);

        return end($candidates) ?: null;
    }
}
