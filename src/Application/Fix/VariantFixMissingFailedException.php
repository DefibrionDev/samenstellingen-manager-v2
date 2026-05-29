<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use RuntimeException;
use Throwable;

final class VariantFixMissingFailedException extends RuntimeException
{
    public static function from(VariantFixMissingPlan $plan, Throwable $previous): self
    {
        return new self(
            sprintf('POST FbComposition mislukt voor %s: %s', $plan->afasItemcode, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
