<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use DomainException;

final class BaseNotFoundException extends DomainException
{
    public static function forId(int $baseId): self
    {
        return new self(sprintf('Base #%d niet gevonden.', $baseId));
    }
}
