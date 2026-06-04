<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class WooIndexRow
{
    /**
     * @param array<int, ?WooIndexCell> $cellsByStore  Map: store_id → cel (of null als afwezig op die store).
     */
    public function __construct(
        public string $afasItemcode,
        public ?string $afasName,
        public array $cellsByStore,
    ) {
    }
}
