<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Woo;

interface WooCommerceClient
{
    /**
     * Alle simple + variable producten (de parents). Variations komen apart
     * via {@see fetchAllVariationsFor()} per variable-parent.
     *
     * @return list<WooProduct>
     */
    public function fetchAllProducts(): array;

    /**
     * @return list<WooProductVariation>
     */
    public function fetchAllVariationsFor(int $variableProductId): array;
}
