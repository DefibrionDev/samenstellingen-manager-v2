<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Naming;

use DomainException;

final class UnknownLanguageException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf(
            "Geen naam-template gedefinieerd voor taal-code '%s' (zie PLAN.md §9.1).",
            $code,
        ));
    }
}
