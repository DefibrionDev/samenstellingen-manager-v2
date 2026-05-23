<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use DomainException;

final class BomCodeAlreadyBlacklistedException extends DomainException
{
    public static function forItemcode(string $itemcode): self
    {
        return new self(sprintf(
            "BOM-itemcode '%s' staat al op de blacklist.",
            $itemcode,
        ));
    }
}
