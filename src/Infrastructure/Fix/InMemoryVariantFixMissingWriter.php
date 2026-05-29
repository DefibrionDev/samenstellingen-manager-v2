<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingFailedException;
use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingPlan;
use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingWriter;
use RuntimeException;

/**
 * Test-double. Verzamelt toegepaste plannen en kan optioneel falen op een
 * specifieke afas_itemcode (voor failure-blokkeert-andere-niet-tests).
 */
final class InMemoryVariantFixMissingWriter implements VariantFixMissingWriter
{
    /** @var list<VariantFixMissingPlan> */
    public array $applied = [];

    public function __construct(private readonly ?string $failOnItemcode = null)
    {
    }

    public function apply(VariantFixMissingPlan $plan): void
    {
        if ($this->failOnItemcode !== null && $plan->afasItemcode === $this->failOnItemcode) {
            throw VariantFixMissingFailedException::from($plan, new RuntimeException('simulated failure'));
        }

        $this->applied[] = $plan;
    }
}
