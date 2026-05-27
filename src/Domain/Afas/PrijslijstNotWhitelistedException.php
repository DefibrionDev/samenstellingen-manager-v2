<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

use DomainException;

final class PrijslijstNotWhitelistedException extends DomainException
{
    public static function forId(string $prijslijstId): self
    {
        return new self(sprintf("Prijslijst '%s' staat niet op de whitelist.", $prijslijstId));
    }
}
