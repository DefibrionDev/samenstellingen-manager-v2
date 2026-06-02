<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Bom\InMemory;

use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use Defibrion\Samenstellingen\Domain\Bom\BomLineReader;

final class InMemoryBomLineReader implements BomLineReader
{
    /** @var list<BomLine> */
    private array $lines = [];

    public function withLines(BomLine ...$lines): self
    {
        $clone = clone $this;
        $clone->lines = array_values($lines);

        return $clone;
    }

    public function findLinesByBomItemcode(string $bomItemcode): array
    {
        $result = [];
        foreach ($this->lines as $line) {
            if ($line->bomItemcode === $bomItemcode) {
                $result[] = $line;
            }
        }

        return $result;
    }

    public function findMaxPrSePerSamenstelling(): array
    {
        $max = [];
        foreach ($this->lines as $line) {
            $cur = $max[$line->samenstellingItemcode] ?? -1;
            if ($line->prSe > $cur) {
                $max[$line->samenstellingItemcode] = $line->prSe;
            }
        }

        return $max;
    }
}
