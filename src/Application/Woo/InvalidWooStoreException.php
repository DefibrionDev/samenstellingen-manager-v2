<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

use RuntimeException;

final class InvalidWooStoreException extends RuntimeException
{
    public static function emptyField(string $field): self
    {
        return new self(sprintf("Veld '%s' mag niet leeg zijn.", $field));
    }

    public static function nonHttpsUrl(string $url): self
    {
        return new self(sprintf("Base-URL moet beginnen met 'https://' (gekregen: '%s').", $url));
    }

    public static function duplicateName(string $name): self
    {
        return new self(sprintf("Er bestaat al een WooCommerce-store met naam '%s'.", $name));
    }
}
