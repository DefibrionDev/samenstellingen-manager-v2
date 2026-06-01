<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

final readonly class SyncPublications
{
    public function __construct(
        public bool $apply = false,
        public ?int $limit = null,
    ) {
    }
}
