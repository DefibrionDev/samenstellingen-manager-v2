<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use RuntimeException;
use Throwable;

final class FamilyHeadParentWriteFailedException extends RuntimeException
{
    public static function from(string $itemcode, Throwable $cause): self
    {
        return new self(
            sprintf("Schrijven van Itemcode_Parent voor '%s' faalde: %s", $itemcode, $cause->getMessage()),
            0,
            $cause,
        );
    }
}
