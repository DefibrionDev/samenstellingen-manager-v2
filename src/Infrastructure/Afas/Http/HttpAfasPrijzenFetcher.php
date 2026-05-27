<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use DateTimeImmutable;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijzenFetcher;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;

/**
 * Haalt actieve prijzen op via twee easylinq-connectors:
 *
 * - `easylinq_prices_saleprice` — baseline (Hoeveelheid=0) en eerste-staffel-prijzen.
 *   Levert per-dag-rijen; we filteren server-side op `date = vandaag` zodat we
 *   alleen de nu-geldige prijs per (item, lijst, debiteur, qty) krijgen.
 * - `easylinq_prices_saleprice_staffel` — staffels (qty > 0). We filteren op
 *   `current=1` om alleen de geldende staffel-prijzen te houden.
 *
 * Performance: we splitsen per prijslijst-id (één call per lijst). Bij een
 * onsplitsten dump probeert AFAS 41k+ rijen in één query te leveren en dropt
 * de connectie rond skip=26000. Per-lijst zijn de pages klein genoeg om
 * server-side in één page te passen.
 */
final readonly class HttpAfasPrijzenFetcher implements AfasPrijzenFetcher
{
    public function __construct(
        private AfasHttpClient $client,
        private PrijslijstWhitelistRepository $whitelist,
    ) {
    }

    public function fetchActive(): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d\T00:00:00');

        $pricelistIds = [];
        foreach ($this->whitelist->findAll() as $entry) {
            $pricelistIds[] = $entry->prijslijstId;
        }
        $pricelistIds = array_values(array_unique($pricelistIds));
        $this->log(sprintf('[prijzen] %d whitelist-pricelists te pullen', count($pricelistIds)));
        if ($pricelistIds === []) {
            $this->log('[prijzen] LEGE whitelist — geen prijzen gepulld. Voeg lijsten toe via `pricelist:whitelist`.');
            return [];
        }

        $result = [];
        foreach ($pricelistIds as $i => $pricelistId) {
            $this->log(sprintf('[prijzen] (%d/%d) pricelist=%s baseline…', $i + 1, count($pricelistIds), $pricelistId));
            $t = microtime(true);
            $baseline = $this->client->getConnectorAll('easylinq_prices_saleprice', [
                'filterfieldids' => 'date,pricelist_id',
                'filtervalues' => $today . ',' . $pricelistId,
                'operatortypes' => '1,1',
            ], 10000);
            $this->log(sprintf('[prijzen]   baseline %d rows in %.1fs', count($baseline), microtime(true) - $t));

            foreach ($baseline as $row) {
                $prijs = $this->fromRow($row, 'Hoeveelheid');
                if ($prijs !== null) {
                    $result[] = $prijs;
                }
            }

            $this->log(sprintf('[prijzen] (%d/%d) pricelist=%s staffel…', $i + 1, count($pricelistIds), $pricelistId));
            $t = microtime(true);
            // Date=today filter: krijgt alleen vandaag-geldige staffels (per definitie current).
            // Zonder date-filter dumpt _staffel honderdduizenden historische dag-voor-dag-rijen.
            $staffel = $this->client->getConnectorAll('easylinq_prices_saleprice_staffel', [
                'filterfieldids' => 'date,pricelist_id',
                'filtervalues' => $today . ',' . $pricelistId,
                'operatortypes' => '1,1',
            ], 10000);
            $this->log(sprintf('[prijzen]   staffel %d rows in %.1fs', count($staffel), microtime(true) - $t));

            foreach ($staffel as $row) {
                $qty = (int) ($row['quantity'] ?? 0);
                if ($qty === 0) {
                    // qty=0 wordt al door saleprice gedekt — voorkom duplicaat.
                    continue;
                }
                $prijs = $this->fromRow($row, 'quantity');
                if ($prijs !== null) {
                    $result[] = $prijs;
                }
            }
        }

        $this->log(sprintf('[prijzen] klaar: %d totaal prijzen', count($result)));

        return $result;
    }

    private function log(string $msg): void
    {
        fwrite(STDERR, '[' . date('H:i:s') . '] ' . $msg . PHP_EOL);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromRow(array $row, string $qtyField): ?AfasPrijs
    {
        $itemcode = isset($row['item_id']) && is_string($row['item_id']) ? trim($row['item_id']) : '';
        $prijslijst = isset($row['pricelist_id']) && is_string($row['pricelist_id']) ? trim($row['pricelist_id']) : '';
        if ($itemcode === '' || $prijslijst === '') {
            return null;
        }

        $debiteurRaw = $row['debtor_id'] ?? null;
        $debiteur = is_string($debiteurRaw) && trim($debiteurRaw) !== '' ? trim($debiteurRaw) : null;

        $cents = $this->toCents($row['price'] ?? null);
        if ($cents === null) {
            return null;
        }

        $qty = (int) ($row[$qtyField] ?? 0);
        $staffel = $qty > 0 ? $qty : null;

        $date = isset($row['date']) && is_string($row['date']) ? substr($row['date'], 0, 10) : '';
        if ($date === '') {
            return null;
        }

        return new AfasPrijs($itemcode, $prijslijst, $debiteur, $cents, $staffel, $date, null);
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
        if (preg_match('/^(-?\d+)(?:\.(\d{1,2}))?\d*$/', $normalized, $m) !== 1) {
            return (int) round(((float) $normalized) * 100);
        }
        $euros = (int) $m[1];
        $centsPart = $m[2] ?? '';
        $cents = $centsPart === '' ? 0 : (int) str_pad($centsPart, 2, '0', STR_PAD_RIGHT);

        return $euros * 100 + ($euros < 0 ? -$cents : $cents);
    }
}
