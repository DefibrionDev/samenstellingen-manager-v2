<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use DomainException;

final class GroupAlreadyExistsException extends DomainException
{
    public static function forName(string $name): self
    {
        return new self(sprintf("Groep '%s' bestaat al.", $name));
    }
}
