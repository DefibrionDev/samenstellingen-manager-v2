<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class ListWooIndex
{
    public function __construct(
        public ?string $storeName,
        public bool $missingOnly,
        public bool $orphanOnly,
    ) {
    }
}
