<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use RuntimeException;
use Throwable;

final class ProductTypeWriteFailedException extends RuntimeException
{
    public static function from(string $itemcode, Throwable $cause): self
    {
        return new self(
            sprintf("Schrijven van producttype voor '%s' faalde: %s", $itemcode, $cause->getMessage()),
            0,
            $cause,
        );
    }

    public static function unresolved(string $itemcode, string $field, string $description): self
    {
        return new self(sprintf(
            "Producttype voor '%s' niet geschreven: description '%s' voor %s onbekend in de AFAS-enum.",
            $itemcode,
            $description,
            $field,
        ));
    }
}
