<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Bom\Http;

use Defibrion\Samenstellingen\Application\Bom\BomComponentStripFailedException;
use Defibrion\Samenstellingen\Application\Bom\BomComponentStripWriter;
use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * Verwijdert één BOM-regel uit een AFAS-samenstelling via PUT FbComposition
 * met `@Action="delete"` op de child `FbCompositionPart.Element.Fields`.
 *
 * Werkende request-shape (geverifieerd in PoC slice 47.0 tegen 11142-EN /
 * 11145-EN). PrSe alléén is niet uniek; door VaIt + ItCd mee te sturen blijft
 * de delete onambiguous als een samenstelling meerdere regels op dezelfde PrSe
 * heeft.
 *
 *   {
 *     "FbComposition": {
 *       "Element": {
 *         "@ItCd": "<samenstelling>",
 *         "Fields": {"ItCd": "<samenstelling>"},
 *         "Objects": {
 *           "FbCompositionPart": {
 *             "Element": [
 *               {"Fields": {"@Action": "delete", "PrSe": <int>, "VaIt": "<Sam|Art>", "ItCd": "<bom-code>"}}
 *             ]
 *           }
 *         }
 *       }
 *     }
 *   }
 */
final readonly class HttpBomComponentStripWriter implements BomComponentStripWriter
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function apply(BomLine $line): void
    {
        $payload = [
            'FbComposition' => [
                'Element' => [
                    '@ItCd' => $line->samenstellingItemcode,
                    'Fields' => ['ItCd' => $line->samenstellingItemcode],
                    'Objects' => [
                        'FbCompositionPart' => [
                            'Element' => [
                                [
                                    'Fields' => [
                                        '@Action' => 'delete',
                                        'PrSe' => $line->prSe,
                                        'VaIt' => $line->vaIt,
                                        'ItCd' => $line->bomItemcode,
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
            throw BomComponentStripFailedException::from($line, $e);
        }
    }
}
