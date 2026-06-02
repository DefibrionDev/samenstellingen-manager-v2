<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use RuntimeException;
use Throwable;

final class BomComponentRestoreFailedException extends RuntimeException
{
    public static function from(BomComponentRestorePlan $plan, Throwable $previous): self
    {
        return new self(
            sprintf(
                'BOM-component %s herstellen in %s (PrSe=%d, VaIt=%s) faalde: %s',
                $plan->bomItemcode,
                $plan->samenstellingItemcode,
                $plan->prSe,
                $plan->vaIt,
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }
}
