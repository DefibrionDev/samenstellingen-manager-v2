<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixNames
{
    public function __construct(
        public bool $apply = false,
        public ?int $limit = null,
    ) {
    }
}
