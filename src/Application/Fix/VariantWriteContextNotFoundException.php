<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use RuntimeException;

final class VariantWriteContextNotFoundException extends RuntimeException
{
    public static function forReference(string $referenceItemcode): self
    {
        return new self(sprintf(
            "Referentie-artikel '%s' niet gevonden in AFAS — kan Grp/CsGc niet spiegelen.",
            $referenceItemcode,
        ));
    }
}
