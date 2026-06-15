<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

/**
 * Webshop-classificatie van één AFAS-artikel: Product_type___01_ ("AED pakket")
 * en Product_type___02_ ("350P"). Descriptions zoals PowerBI_Item ze levert;
 * lege waarden worden naar null genormaliseerd. Zie PLAN-AFAS.md §35.
 */
final readonly class ItemProductTypes
{
    public string $itemcode;
    public ?string $productType01;
    public ?string $productType02;

    public function __construct(string $itemcode, ?string $productType01, ?string $productType02)
    {
        $this->itemcode = trim($itemcode);
        $this->productType01 = self::normalise($productType01);
        $this->productType02 = self::normalise($productType02);
    }

    private static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
