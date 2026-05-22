<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class BomItem
{
    public function __construct(
        public string $itemcode,
        public string $name,
    ) {
    }
}
