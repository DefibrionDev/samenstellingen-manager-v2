<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijzenFetcher;

/**
 * Haalt `Get_Prijzen` op en filtert op actieve rijen: Einddatum leeg of in
 * de toekomst. Negeert ongeldige rijen (lege itemcode/prijslijst).
 *
 * AFAS levert Verkoopprijs als decimaal-getal (bv. "1995" of "2334.51").
 * Slaan we op als integer cents om float-rounding te vermijden.
 */
final readonly class HttpAfasPrijzenFetcher implements AfasPrijzenFetcher
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function fetchActive(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $rows = $this->client->getConnectorAll('Get_Prijzen');

        $result = [];
        foreach ($rows as $row) {
            $itemcode = isset($row['Itemcode']) && is_string($row['Itemcode']) ? trim($row['Itemcode']) : '';
            $prijslijst = isset($row['Prijslijst']) && is_string($row['Prijslijst']) ? trim($row['Prijslijst']) : '';
            if ($itemcode === '' || $prijslijst === '') {
                continue;
            }

            $endDate = isset($row['Einddatum']) && is_string($row['Einddatum']) ? substr($row['Einddatum'], 0, 10) : '';
            // Filter actieve rijen: einddatum leeg of niet vóór vandaag.
            if ($endDate !== '' && $endDate < $today) {
                continue;
            }

            $debiteurRaw = $row['Debiteur'] ?? null;
            $debiteur = is_string($debiteurRaw) && trim($debiteurRaw) !== '' ? trim($debiteurRaw) : null;

            $verkoopprijsRaw = $row['Verkoopprijs'] ?? null;
            $cents = $this->toCents($verkoopprijsRaw);
            if ($cents === null) {
                continue; // geen leesbare prijs → overslaan
            }

            $staffelRaw = $row['Staffelprijs'] ?? null;
            $staffel = is_numeric($staffelRaw) ? (int) $staffelRaw : null;

            $startDate = isset($row['Begindatum']) && is_string($row['Begindatum']) ? substr($row['Begindatum'], 0, 10) : '';
            if ($startDate === '') {
                continue;
            }

            $result[] = new AfasPrijs(
                $itemcode,
                $prijslijst,
                $debiteur,
                $cents,
                $staffel,
                $startDate,
                $endDate === '' ? null : $endDate,
            );
        }

        return $result;
    }

    private function toCents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            return (int) round($value * 100);
        }
        if (!is_string($value)) {
            return null;
        }
        $normalized = str_replace(',', '.', trim($value));
        if (!is_numeric($normalized)) {
            return null;
        }

        // Werk via string-arithmetic om float-rounding te vermijden.
        if (preg_match('/^(-?\d+)(?:\.(\d{1,2}))?\d*$/', $normalized, $m) !== 1) {
            // Heel zelden — meer dan 2 decimalen. Rond op cents af.
            return (int) round(((float) $normalized) * 100);
        }
        $euros = (int) $m[1];
        $centsPart = $m[2] ?? '';
        $cents = $centsPart === '' ? 0 : (int) str_pad($centsPart, 2, '0', STR_PAD_RIGHT);

        return $euros * 100 + ($euros < 0 ? -$cents : $cents);
    }
}
