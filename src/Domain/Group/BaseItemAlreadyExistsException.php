<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use DomainException;

final class BaseItemAlreadyExistsException extends DomainException
{
    public static function forItemcodeInBase(string $itemcode, int $baseId): self
    {
        return new self(sprintf("Item met itemcode '%s' bestaat al in base #%d.", $itemcode, $baseId));
    }
}
