<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Woo;

final readonly class WooCommerceStore
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $baseUrl,
        public string $consumerKey,
        public string $consumerSecret,
        public string $afasItemcodeMetaKey = '_afas_itemcode',
    ) {
    }
}
