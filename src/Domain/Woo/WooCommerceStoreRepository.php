<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Woo;

interface WooCommerceStoreRepository
{
    public function save(WooCommerceStore $store): WooCommerceStore;

    public function findByName(string $name): ?WooCommerceStore;

    /**
     * @return list<WooCommerceStore>
     */
    public function findAll(): array;

    /**
     * @throws WooCommerceStoreNotFoundException
     */
    public function delete(int $id): void;
}
