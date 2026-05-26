<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use DomainException;

final class AfasSamenstellingNotInSnapshotException extends DomainException
{
    public static function forItemcode(string $itemcode): self
    {
        return new self(sprintf(
            "AFAS-samenstelling '%s' staat niet in de lokale snapshot — draai eerst `afas:pull`.",
            $itemcode,
        ));
    }
}
