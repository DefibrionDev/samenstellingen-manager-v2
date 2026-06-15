<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use Defibrion\Samenstellingen\Domain\Afas\ItemProductTypes;
use Defibrion\Samenstellingen\Domain\Afas\PowerBiItemFetcher;

final readonly class HttpPowerBiItemFetcher implements PowerBiItemFetcher
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function fetchProductTypes(): array
    {
        $result = [];
        foreach ($this->client->getConnectorAll('PowerBI_Item') as $row) {
            $itemcode = $row['Itemcode'] ?? null;
            if (!is_string($itemcode) || trim($itemcode) === '') {
                continue;
            }

            $type01 = $row['Product_type___01_'] ?? null;
            $type02 = $row['Product_type___02_'] ?? null;

            $entry = new ItemProductTypes(
                $itemcode,
                is_scalar($type01) ? (string) $type01 : null,
                is_scalar($type02) ? (string) $type02 : null,
            );

            // Lege items overslaan: replaceSnapshot heeft de kolommen al op null
            // gezet, dus een leeg-update voegt niets toe en houdt de pass licht.
            if ($entry->productType01 === null && $entry->productType02 === null) {
                continue;
            }

            $result[] = $entry;
        }

        return $result;
    }
}
