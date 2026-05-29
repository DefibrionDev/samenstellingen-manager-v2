<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\NameFixFailedException;
use Defibrion\Samenstellingen\Application\Fix\NameFixPlan;
use Defibrion\Samenstellingen\Application\Fix\NameFixWriter;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * Schrijft de canonical naam naar AFAS via UpdateConnector FbComposition (PUT).
 * Onze samenstellingen zijn type_id=7 — die routen naar FbComposition, niet
 * FbItemArticle (waar AFAS reageert met "Artikel niet gevonden"). Zie composite
 * routing in afas-connector-tools (apply-philips.php e.a.).
 *
 * ItCd staat zowel als @ItCd-attribuut (key) als binnen Fields — anders krijgen we 500.
 *
 *   {"FbComposition": {"Element": {"@ItCd": "<code>", "Fields": {"ItCd": "<code>", "Ds": "<naam>"}}}}
 */
final readonly class HttpFbCompositionNameWriter implements NameFixWriter
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function apply(NameFixPlan $plan): void
    {
        $payload = [
            'FbComposition' => [
                'Element' => [
                    '@ItCd' => $plan->afasItemcode,
                    'Fields' => [
                        'ItCd' => $plan->afasItemcode,
                        'Ds' => $plan->targetName,
                    ],
                ],
            ],
        ];

        try {
            $this->client->updateConnector('FbComposition', $payload);
        } catch (Throwable $e) {
            throw NameFixFailedException::from($plan, $e);
        }
    }
}
