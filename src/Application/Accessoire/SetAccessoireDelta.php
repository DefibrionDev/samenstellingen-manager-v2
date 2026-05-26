<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Accessoire;

final readonly class SetAccessoireDelta
{
    public function __construct(
        public string $itemcode,
        public int $deltaCents,
    ) {
    }
}
