<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use DomainException;

final class BaseAlreadyExistsException extends DomainException
{
    public static function forItemcodeInGroup(string $itemcode, string $groupName): self
    {
        return new self(sprintf(
            "Base met itemcode '%s' bestaat al in groep '%s'.",
            $itemcode,
            $groupName,
        ));
    }
}
