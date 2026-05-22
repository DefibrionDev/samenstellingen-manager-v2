<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use DomainException;

final class AmbiguousMatchException extends DomainException
{
    /**
     * @param list<string> $afasItemcodes
     */
    public static function forVariant(int $variantId, array $afasItemcodes): self
    {
        return new self(sprintf(
            'Meerdere AFAS-samenstellingen (%s) hebben dezelfde BOM als variant #%d. Verwacht: één unieke match.',
            implode(', ', $afasItemcodes),
            $variantId,
        ));
    }
}
