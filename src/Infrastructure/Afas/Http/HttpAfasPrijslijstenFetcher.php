<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstenFetcher;

/**
 * Haalt `Get_Prijslijsten` op. Levert 29 statische rijen (id, omschrijving).
 * Lege of ontbrekende omschrijving valt terug op de ID, zodat we de rij niet
 * stilletjes wegfilteren — een prijslijst zonder naam in AFAS blijft zichtbaar
 * met z'n ID als label.
 */
final readonly class HttpAfasPrijslijstenFetcher implements AfasPrijslijstenFetcher
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function fetchAll(): array
    {
        $rows = $this->client->getConnectorAll('Get_Prijslijsten');

        $result = [];
        foreach ($rows as $row) {
            $id = isset($row['id']) && is_string($row['id']) ? trim($row['id']) : '';
            if ($id === '') {
                continue;
            }
            $omschrijvingRaw = $row['Omschrijving'] ?? null;
            $omschrijving = is_string($omschrijvingRaw) ? trim($omschrijvingRaw) : '';
            if ($omschrijving === '') {
                $omschrijving = $id;
            }

            $result[] = new AfasPrijslijst($id, $omschrijving);
        }

        return $result;
    }
}
