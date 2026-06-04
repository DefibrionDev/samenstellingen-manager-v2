<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class AddWooStore
{
    public function __construct(
        public string $name,
        public string $baseUrl,
        public string $consumerKey,
        public string $consumerSecret,
        public string $afasItemcodeMetaKey = '_afas_itemcode',
    ) {
    }
}
