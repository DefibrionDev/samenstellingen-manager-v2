<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class MissingCbsRow
{
    public function __construct(
        public string $itemcode,
        public string $name,
        public ?string $itemcodeParent,
    ) {
    }
}
