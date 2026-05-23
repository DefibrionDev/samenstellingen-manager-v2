<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

final readonly class BlacklistBomCode
{
    public function __construct(
        public string $itemcode,
        public string $reason,
    ) {
    }
}
