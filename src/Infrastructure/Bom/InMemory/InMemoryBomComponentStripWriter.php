<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Bom\InMemory;

use Defibrion\Samenstellingen\Application\Bom\BomComponentStripFailedException;
use Defibrion\Samenstellingen\Application\Bom\BomComponentStripWriter;
use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use RuntimeException;

final class InMemoryBomComponentStripWriter implements BomComponentStripWriter
{
    /** @var list<BomLine> */
    public array $applied = [];

    /** @var array<string, true> "<samenstelling>|<bomItemcode>" → moet falen */
    private array $failOn = [];

    public function failOn(string $samenstellingItemcode, string $bomItemcode): self
    {
        $this->failOn[$samenstellingItemcode . '|' . $bomItemcode] = true;

        return $this;
    }

    public function apply(BomLine $line): void
    {
        $key = $line->samenstellingItemcode . '|' . $line->bomItemcode;
        if (isset($this->failOn[$key])) {
            throw BomComponentStripFailedException::from($line, new RuntimeException('forced failure'));
        }
        $this->applied[] = $line;
    }
}
