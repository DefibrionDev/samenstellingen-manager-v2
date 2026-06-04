<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class PullWooStore
{
    /**
     * @param string|null $storeName  Naam van één store; null = alle geregistreerde stores.
     */
    public function __construct(public ?string $storeName)
    {
    }
}
