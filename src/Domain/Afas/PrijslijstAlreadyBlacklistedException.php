<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use DomainException;

final class PrijslijstAlreadyBlacklistedException extends DomainException
{
    public static function forId(string $prijslijstId): self
    {
        return new self(sprintf("Prijslijst '%s' staat al op de blacklist.", $prijslijstId));
    }
}
