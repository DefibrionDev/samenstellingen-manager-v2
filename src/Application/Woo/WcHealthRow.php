<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class WcHealthRow
{
    /**
     * @param array<int, WcHealthCell> $cellsByStore  Map: store_id → cel
     */
    public function __construct(
        public string $afasItemcode,
        public string $expectedType,
        public array $cellsByStore,
    ) {
    }
}
