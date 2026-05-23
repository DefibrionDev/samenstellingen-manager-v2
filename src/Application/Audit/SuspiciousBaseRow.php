<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class SuspiciousBaseRow
{
    /**
     * @param list<string> $bom
     */
    public function __construct(
        public string $afasItemcode,
        public string $name,
        public string $expectedAccessoireItemcode,
        public string $expectedAccessoireLabel,
        public array $bom,
    ) {
    }
}
