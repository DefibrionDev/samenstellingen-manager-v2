<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class WcHealthCell
{
    public function __construct(
        public ?int $wcProductId,
        public ?string $actualType,
        public ?string $status,
        public WcHealthStatus $healthStatus,
    ) {
    }
}
