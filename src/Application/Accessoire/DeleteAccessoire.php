<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Accessoire;

final readonly class DeleteAccessoire
{
    public function __construct(public string $itemcode)
    {
    }
}
