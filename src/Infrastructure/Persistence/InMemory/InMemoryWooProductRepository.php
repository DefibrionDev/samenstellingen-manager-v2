<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;

final class InMemoryWooProductRepository implements WooProductRepository
{
    /** @var array<int, list<WooProduct|WooProductVariation>> store_id → items */
    private array $byStore = [];

    public function replaceForStore(int $storeId, array $items): void
    {
        $this->byStore[$storeId] = $items;
    }

    public function findAllForStore(int $storeId): array
    {
        return $this->byStore[$storeId] ?? [];
    }

    public function findByAfasItemcode(string $afasItemcode): array
    {
        if ($afasItemcode === '') {
            return [];
        }
        $result = [];
        ksort($this->byStore);
        foreach ($this->byStore as $storeId => $items) {
            foreach ($items as $item) {
                if ($item->afasItemcode === $afasItemcode) {
                    $result[] = ['store_id' => $storeId, 'product' => $item];
                }
            }
        }

        return $result;
    }
}
