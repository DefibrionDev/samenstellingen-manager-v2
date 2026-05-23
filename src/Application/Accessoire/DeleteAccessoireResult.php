<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Accessoire;

final readonly class DeleteAccessoireResult
{
    /**
     * @param list<string> $affectedFamilyHeads Family-heads waarvan varianten zijn geregenereerd.
     */
    public function __construct(
        public string $itemcode,
        public array $affectedFamilyHeads,
    ) {
    }
}
