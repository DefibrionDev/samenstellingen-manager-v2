<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Accessoire;

use DomainException;

final class AccessoireNotFoundException extends DomainException
{
    public static function forItemcode(string $itemcode): self
    {
        return new self(sprintf(
            "Accessoire met itemcode '%s' bestaat niet in de catalogus.",
            $itemcode,
        ));
    }
}
