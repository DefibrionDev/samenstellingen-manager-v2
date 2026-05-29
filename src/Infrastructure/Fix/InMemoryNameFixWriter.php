<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\NameFixFailedException;
use Defibrion\Samenstellingen\Application\Fix\NameFixPlan;
use Defibrion\Samenstellingen\Application\Fix\NameFixWriter;
use RuntimeException;

final class InMemoryNameFixWriter implements NameFixWriter
{
    /** @var list<NameFixPlan> */
    public array $applied = [];

    public function __construct(private readonly ?string $failOnItemcode = null)
    {
    }

    public function apply(NameFixPlan $plan): void
    {
        if ($this->failOnItemcode !== null && $plan->afasItemcode === $this->failOnItemcode) {
            throw NameFixFailedException::from($plan, new RuntimeException('simulated failure'));
        }
        $this->applied[] = $plan;
    }
}
