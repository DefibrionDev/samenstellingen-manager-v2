<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use RuntimeException;
use Throwable;

final class PriceFixFailedException extends RuntimeException
{
    public static function from(PriceFixPlan $plan, Throwable $previous): self
    {
        $msg = sprintf(
            'PUT FbSalesPrice mislukt voor %s in lijst %s (staffel %s): %s',
            $plan->variantItemcode,
            $plan->prijslijstId,
            $plan->staffelAantal !== null ? (string) $plan->staffelAantal : 'basis',
            $previous->getMessage(),
        );

        return new self($msg, 0, $previous);
    }
}
