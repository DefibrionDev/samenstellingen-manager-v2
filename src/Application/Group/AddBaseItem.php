<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class AddBaseItem
{
    public function __construct(
        public int $baseId,
        public string $itemcode,
        public string $name,
    ) {
    }
}
