<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use DomainException;

final class GroupNotFoundException extends DomainException
{
    public static function forName(string $name): self
    {
        return new self(sprintf("Groep '%s' niet gevonden.", $name));
    }

    public static function forFamilyHeadItemcode(string $familyHeadItemcode): self
    {
        return new self(sprintf(
            'Geen groep gevonden met family-head itemcode %s.',
            $familyHeadItemcode,
        ));
    }
}
