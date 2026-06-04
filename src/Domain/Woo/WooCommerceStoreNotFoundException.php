<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Woo;

use RuntimeException;

final class WooCommerceStoreNotFoundException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self(sprintf("WooCommerce-store '%s' bestaat niet.", $name));
    }
}
