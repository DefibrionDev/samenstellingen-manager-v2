<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class PullWooStoreResult
{
    /**
     * @param array<string, int> $itemsByStore  Per store-naam het aantal opgehaalde items
     *                                          (simple + variable-parents + variations).
     */
    public function __construct(public array $itemsByStore)
    {
    }
}
