<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use DomainException;

final class BaseAlreadyExistsException extends DomainException
{
    public static function forNameInGroup(string $name, string $familyHeadItemcode): self
    {
        return new self(sprintf(
            "Base met naam '%s' bestaat al in groep %s.",
            $name,
            $familyHeadItemcode,
        ));
    }
}
