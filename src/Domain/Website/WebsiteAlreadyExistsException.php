<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Website;

use DomainException;

final class WebsiteAlreadyExistsException extends DomainException
{
    public static function forName(string $name): self
    {
        return new self(sprintf("Website met naam '%s' bestaat al.", $name));
    }
}
