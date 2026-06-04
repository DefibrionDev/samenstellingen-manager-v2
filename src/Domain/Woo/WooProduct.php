<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Woo;

/**
 * Eén WooCommerce-product zoals het in onze snapshot landt. Dekt zowel
 * simple-producten als variable-parents (`type='variable'`) — variations
 * komen apart via {@see WooProductVariation}.
 */
final readonly class WooProduct
{
    public function __construct(
        public int $wcProductId,
        public string $type,
        public ?string $sku,
        public string $name,
        public string $status,
        public ?string $permalink,
        public ?string $afasItemcode,
    ) {
    }
}
