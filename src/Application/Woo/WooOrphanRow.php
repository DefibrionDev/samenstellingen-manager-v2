<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class WooOrphanRow
{
    public function __construct(
        public int $storeId,
        public string $storeName,
        public int $wcProductId,
        public string $wcType,
        public ?string $sku,
        public string $name,
        public string $status,
        public ?string $afasItemcode,
        public ?string $permalink,
    ) {
    }
}
