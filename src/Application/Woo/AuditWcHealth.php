<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class AuditWcHealth
{
    public function __construct(public ?string $storeName)
    {
    }
}
