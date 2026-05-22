<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use DomainException;

final class AccessoireAlreadyLinkedException extends DomainException
{
    public static function forAccessoireInGroup(string $accessoireItemcode, string $groupName): self
    {
        return new self(sprintf(
            "Accessoire '%s' is al gekoppeld aan groep '%s'.",
            $accessoireItemcode,
            $groupName,
        ));
    }
}
