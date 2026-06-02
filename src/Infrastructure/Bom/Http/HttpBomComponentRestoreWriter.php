<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Bom\Http;

use Defibrion\Samenstellingen\Application\Bom\BomComponentRestoreFailedException;
use Defibrion\Samenstellingen\Application\Bom\BomComponentRestorePlan;
use Defibrion\Samenstellingen\Application\Bom\BomComponentRestoreWriter;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * Voegt een BOM-regel toe aan een AFAS-samenstelling via PUT FbComposition
 * met `@Action="insert"` op de child Fields. Werkende request-shape
 * geverifieerd in PoC slice 47.0 (repair-script).
 *
 *   {
 *     "FbComposition": {
 *       "Element": {
 *         "@ItCd": "<samenstelling>",
 *         "Fields": {"ItCd": "<samenstelling>"},
 *         "Objects": {
 *           "FbCompositionPart": {
 *             "Element": [
 *               {"Fields": {"@Action": "insert", "VaIt": "<Sam|Art>", "ItCd": "<bom-code>", "QuUn": 1, "Qu": 1, "PrSe": <int>}}
 *             ]
 *           }
 *         }
 *       }
 *     }
 *   }
 */
final readonly class HttpBomComponentRestoreWriter implements BomComponentRestoreWriter
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function apply(BomComponentRestorePlan $plan): void
    {
        $payload = [
            'FbComposition' => [
                'Element' => [
                    '@ItCd' => $plan->samenstellingItemcode,
                    'Fields' => ['ItCd' => $plan->samenstellingItemcode],
                    'Objects' => [
                        'FbCompositionPart' => [
                            'Element' => [
                                [
                                    'Fields' => [
                                        '@Action' => 'insert',
                                        'VaIt' => $plan->vaIt,
                                        'ItCd' => $plan->bomItemcode,
                                        'QuUn' => 1,
                                        'Qu' => 1,
                                        'PrSe' => $plan->prSe,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->client->updateConnector('FbComposition', $payload);
        } catch (Throwable $e) {
            throw BomComponentRestoreFailedException::from($plan, $e);
        }
    }
}
