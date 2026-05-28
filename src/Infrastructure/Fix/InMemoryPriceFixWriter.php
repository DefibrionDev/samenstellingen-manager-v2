<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\PriceFixFailedException;
use Defibrion\Samenstellingen\Application\Fix\PriceFixPlan;
use Defibrion\Samenstellingen\Application\Fix\PriceFixWriter;
use RuntimeException;

/**
 * Test-writer: registreert toegepaste plannen, doet geen netwerk-call.
 * Kan via `$failOnVariant` één rij laten falen om error-handling te testen.
 */
final class InMemoryPriceFixWriter implements PriceFixWriter
{
    /** @var list<PriceFixPlan> */
    public array $applied = [];
    /** @var list<PriceFixPlan> */
    public array $inserted = [];

    public function __construct(private readonly ?string $failOnVariant = null)
    {
    }

    public function apply(PriceFixPlan $plan): void
    {
        if ($this->failOnVariant !== null && $plan->variantItemcode === $this->failOnVariant) {
            throw PriceFixFailedException::from($plan, new RuntimeException('simulated failure'));
        }
        $this->applied[] = $plan;
    }

    public function insert(PriceFixPlan $plan): void
    {
        if ($this->failOnVariant !== null && $plan->variantItemcode === $this->failOnVariant) {
            throw PriceFixFailedException::from($plan, new RuntimeException('simulated failure'));
        }
        $this->inserted[] = $plan;
    }
}
