<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Publications;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateReader;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;

/**
 * Pull Get_Artikelen één keer en map de bekende NL-website-vrije-velden
 * (Sync_Reseller_NL + Tonen_Reseller_NL) naar hun UUID's. Voor andere
 * websites (geen alias in Get_Artikelen) blijft de map leeg → handler
 * skipt niet en PUT'et alsnog.
 *
 * Als Defibrion meer free-field aliassen in Get_Artikelen ontsluit, breid
 * dan COLUMN_TO_UUID uit met de bijbehorende mapping.
 */
final readonly class HttpAfasFreeFieldStateReader implements AfasFreeFieldStateReader
{
    private const COLUMN_TO_UUID = [
        'Sync_Reseller_NL' => 'U4E3E32DEFB374A1BA9F8680B8C405907',
        'Tonen_Reseller_NL' => 'UD77EC755E2F1404EB184A956685A7C0C',
    ];

    public function __construct(private AfasHttpClient $client)
    {
    }

    public function readAll(): array
    {
        $state = [];
        foreach ($this->client->getConnectorAll('Get_Artikelen') as $row) {
            $itemcode = $row['Itemcode'] ?? null;
            if (!is_string($itemcode) || $itemcode === '') {
                continue;
            }
            $flags = [];
            foreach (self::COLUMN_TO_UUID as $column => $uuid) {
                $raw = $row[$column] ?? null;
                if (is_scalar($raw)) {
                    $flags[$uuid] = (string) $raw === '1';
                }
            }
            if ($flags !== []) {
                $state[$itemcode] = $flags;
            }
        }

        return $state;
    }
}
