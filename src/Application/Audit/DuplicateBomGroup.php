<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class DuplicateBomGroup
{
    /**
     * @param list<array{itemcode: string, name: string}> $members
     */
    public function __construct(
        public string $fingerprint,
        public array $members,
    ) {
    }
}
