<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\PriceFixFailedException;
use Defibrion\Samenstellingen\Application\Fix\PriceFixPlan;
use Defibrion\Samenstellingen\Application\Fix\PriceFixWriter;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * Schrijft prijs-correcties naar AFAS via UpdateConnector `FbSalesPrice` (PUT).
 *
 * Payload-structuur (PoC live geverifieerd op 27-05-2026 met itemcode
 * 10041-60212 in lijst 003 — €1520 → €1450, HTTP 201):
 *
 *   {"FbSalesPrice": {"Element": {"Fields": {
 *     "VaIt": "7",          // 7 = Samenstelling (universeel voor onze varianten)
 *     "ItCd": variantItemcode,
 *     "PrLi": prijslijstId,
 *     "BiUn": "STK",
 *     "CuId": "EUR",
 *     "DaBg": beginDate,    // bestaande begindatum behouden (geen historie-corruptie)
 *     "Pric": targetCents/100,
 *     ...staffel-velden indien van toepassing
 *   }}}}
 *
 * Staffel-support: bij `staffelAantal > 0` voegen we `CrPr=true` en `Am=N` toe
 * zodat de juiste staffel-rij wordt geüpdatet ipv de baseline.
 */
final readonly class HttpFbSalesPriceWriter implements PriceFixWriter
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function apply(PriceFixPlan $plan): void
    {
        try {
            $this->client->updateConnector('FbSalesPrice', $this->payload($plan));
        } catch (Throwable $e) {
            throw PriceFixFailedException::from($plan, $e);
        }
    }

    public function insert(PriceFixPlan $plan): void
    {
        try {
            $this->client->insertConnector('FbSalesPrice', $this->payload($plan));
        } catch (Throwable $e) {
            throw PriceFixFailedException::from($plan, $e);
        }
    }

    /**
     * @return array{FbSalesPrice: array{Element: array{Fields: array<string, mixed>}}}
     */
    private function payload(PriceFixPlan $plan): array
    {
        $fields = [
            'VaIt' => '7',
            'ItCd' => $plan->variantItemcode,
            'PrLi' => $plan->prijslijstId,
            'BiUn' => 'STK',
            'CuId' => 'EUR',
            'DaBg' => $plan->beginDate,
            'Pric' => $plan->targetCents / 100,
        ];
        if ($plan->staffelAantal !== null && $plan->staffelAantal > 0) {
            $fields['CrPr'] = true;
            $fields['Am'] = $plan->staffelAantal;
        }

        return ['FbSalesPrice' => ['Element' => ['Fields' => $fields]]];
    }
}
