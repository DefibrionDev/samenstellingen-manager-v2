<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Bom\InMemory;

use Defibrion\Samenstellingen\Application\Bom\BomComponentRestoreFailedException;
use Defibrion\Samenstellingen\Application\Bom\BomComponentRestorePlan;
use Defibrion\Samenstellingen\Application\Bom\BomComponentRestoreWriter;
use RuntimeException;

final class InMemoryBomComponentRestoreWriter implements BomComponentRestoreWriter
{
    /** @var list<BomComponentRestorePlan> */
    public array $applied = [];

    /** @var array<string, true> "<samenstelling>|<bomItemcode>" → forceer fail */
    private array $failOn = [];

    public function failOn(string $samenstellingItemcode, string $bomItemcode): self
    {
        $this->failOn[$samenstellingItemcode . '|' . $bomItemcode] = true;

        return $this;
    }

    public function apply(BomComponentRestorePlan $plan): void
    {
        $key = $plan->samenstellingItemcode . '|' . $plan->bomItemcode;
        if (isset($this->failOn[$key])) {
            throw BomComponentRestoreFailedException::from($plan, new RuntimeException('forced failure'));
        }
        $this->applied[] = $plan;
    }
}
