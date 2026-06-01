<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

use RuntimeException;
use Throwable;

final class PublicationSyncFailedException extends RuntimeException
{
    public static function from(PublicationSyncPlan $plan, Throwable $previous): self
    {
        return new self(
            sprintf('PUT FbComposition mislukt voor %s: %s', $plan->afasItemcode, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
