<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use RuntimeException;
use Throwable;

final class BomComponentStripFailedException extends RuntimeException
{
    public static function from(BomLine $line, Throwable $previous): self
    {
        return new self(
            sprintf(
                'BOM-component %s strippen uit %s (PrSe=%d, VaIt=%s) faalde: %s',
                $line->bomItemcode,
                $line->samenstellingItemcode,
                $line->prSe,
                $line->vaIt,
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }
}
