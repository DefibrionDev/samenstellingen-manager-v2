<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Accessoire;

use DomainException;

final class AccessoireAlreadyExistsException extends DomainException
{
    public static function forItemcode(string $itemcode): self
    {
        return new self(sprintf(
            "Accessoire met itemcode '%s' bestaat al in de catalogus.",
            $itemcode,
        ));
    }
}
