<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Woo;

/**
 * Eén variation van een variable-parent. Aparte VO van {@see WooProduct} omdat
 * de WC REST-endpoints verschillen (variations zitten onder
 * `/products/{parentId}/variations`) en omdat variations altijd een
 * parentId hebben.
 */
final readonly class WooProductVariation
{
    public function __construct(
        public int $wcProductId,
        public int $parentId,
        public ?string $sku,
        public string $name,
        public string $status,
        public ?string $permalink,
        public ?string $afasItemcode,
    ) {
    }
}
