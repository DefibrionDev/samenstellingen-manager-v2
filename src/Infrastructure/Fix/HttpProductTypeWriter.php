<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\ProductTypeWriteFailedException;
use Defibrion\Samenstellingen\Application\Fix\ProductTypeWriter;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * PUT FbComposition met de twee producttype-vrijevelden. De descriptions
 * ("AED pakket" / "350P") worden via de gedeelde resolver naar AFAS enum-id's
 * vertaald; een onbekende description wordt geweigerd i.p.v. het veld leeg te
 * schrijven. Zelfde UUID's als variant-generatie. Zie PLAN-AFAS.md §35.
 */
final readonly class HttpProductTypeWriter implements ProductTypeWriter
{
    private const FF_PRODUCT_TYPE = 'U5C3C0BC348244F0F97425794CE3FB4A8';
    private const FF_SUBCATEGORIE = 'U79C8521E4FDA2AC22FF895BD89B6D273';

    public function __construct(
        private AfasHttpClient $client,
        private FbCompositionEnumResolver $resolver,
    ) {
    }

    public function write(string $itemcode, string $productType01, string $productType02): void
    {
        $id01 = $this->resolver->resolve(self::FF_PRODUCT_TYPE, $productType01);
        if ($id01 === '') {
            throw ProductTypeWriteFailedException::unresolved($itemcode, 'Product_type 01', $productType01);
        }
        $id02 = $this->resolver->resolve(self::FF_SUBCATEGORIE, $productType02);
        if ($id02 === '') {
            throw ProductTypeWriteFailedException::unresolved($itemcode, 'Product_type 02', $productType02);
        }

        try {
            $this->client->updateConnector('FbComposition', [
                'FbComposition' => [
                    'Element' => [
                        'Fields' => [
                            'ItCd' => $itemcode,
                            self::FF_PRODUCT_TYPE => $id01,
                            self::FF_SUBCATEGORIE => $id02,
                        ],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            throw ProductTypeWriteFailedException::from($itemcode, $e);
        }
    }
}
