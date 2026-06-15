<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\ItemProductTypes;
use Defibrion\Samenstellingen\Domain\Afas\PowerBiItemFetcher;

final class InMemoryPowerBiItemFetcher implements PowerBiItemFetcher
{
    /** @var list<ItemProductTypes> */
    private array $productTypes = [];

    public function withProductTypes(ItemProductTypes ...$productTypes): self
    {
        $clone = clone $this;
        $clone->productTypes = array_values($productTypes);

        return $clone;
    }

    public function fetchProductTypes(): array
    {
        return $this->productTypes;
    }
}
