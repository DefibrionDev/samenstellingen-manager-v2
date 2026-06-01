<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Publications;

use Defibrion\Samenstellingen\Application\Publications\PublicationSyncFailedException;
use Defibrion\Samenstellingen\Application\Publications\PublicationSyncPlan;
use Defibrion\Samenstellingen\Application\Publications\PublicationSyncWriter;
use RuntimeException;

final class InMemoryPublicationSyncWriter implements PublicationSyncWriter
{
    /** @var list<PublicationSyncPlan> */
    public array $applied = [];

    public function __construct(private readonly ?string $failOnItemcode = null)
    {
    }

    public function apply(PublicationSyncPlan $plan): void
    {
        if ($this->failOnItemcode !== null && $plan->afasItemcode === $this->failOnItemcode) {
            throw PublicationSyncFailedException::from($plan, new RuntimeException('simulated failure'));
        }

        $this->applied[] = $plan;
    }
}
