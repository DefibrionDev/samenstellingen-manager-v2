<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Woo;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceClient;
use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;

final class InMemoryWooCommerceClient implements WooCommerceClient
{
    /**
     * @param list<WooProduct>                   $products
     * @param array<int, list<WooProductVariation>> $variationsByParentId
     */
    public function __construct(
        private readonly array $products = [],
        private readonly array $variationsByParentId = [],
    ) {
    }

    public function fetchAllProducts(): array
    {
        return $this->products;
    }

    public function fetchAllVariationsFor(int $variableProductId): array
    {
        return $this->variationsByParentId[$variableProductId] ?? [];
    }
}
