<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use RuntimeException;

final class InMemoryWooCommerceStoreRepository implements WooCommerceStoreRepository
{
    private int $nextId = 1;

    /** @var array<int, WooCommerceStore> */
    private array $byId = [];

    public function save(WooCommerceStore $store): WooCommerceStore
    {
        foreach ($this->byId as $existing) {
            if ($existing->name === $store->name && $existing->id !== $store->id) {
                throw new RuntimeException(sprintf("WooCommerce-store '%s' bestaat al.", $store->name));
            }
        }

        $id = $store->id ?? $this->nextId++;
        $saved = new WooCommerceStore(
            $id,
            $store->name,
            $store->baseUrl,
            $store->consumerKey,
            $store->consumerSecret,
            $store->afasItemcodeMetaKey,
        );
        $this->byId[$id] = $saved;

        return $saved;
    }

    public function findByName(string $name): ?WooCommerceStore
    {
        foreach ($this->byId as $store) {
            if ($store->name === $name) {
                return $store;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        $stores = array_values($this->byId);
        usort($stores, static fn (WooCommerceStore $a, WooCommerceStore $b) => strcmp($a->name, $b->name));

        return $stores;
    }

    public function delete(int $id): void
    {
        if (!isset($this->byId[$id])) {
            throw WooCommerceStoreNotFoundException::forName('id=' . $id);
        }
        unset($this->byId[$id]);
    }
}
