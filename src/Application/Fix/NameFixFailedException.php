<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use RuntimeException;
use Throwable;

final class NameFixFailedException extends RuntimeException
{
    public static function from(NameFixPlan $plan, Throwable $previous): self
    {
        return new self(
            sprintf('PUT FbItemArticle.Ds mislukt voor %s: %s', $plan->afasItemcode, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
