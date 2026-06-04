<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProductRepository;

/**
 * Ververst de WooCommerce-snapshot voor één of alle geregistreerde stores.
 * Per store: fetch alle simple + variable producten, plus de variations van
 * elke variable-parent. Snapshot-replace overschrijft eerdere data atomair
 * in de repository. Zie PLAN.md §3 + §7.
 */
final readonly class PullWooStoreHandler
{
    public function __construct(
        private WooCommerceStoreRepository $stores,
        private WooProductRepository $products,
        private WooCommerceClientFactory $clientFactory,
    ) {
    }

    public function __invoke(PullWooStore $command): PullWooStoreResult
    {
        $targets = $this->resolveTargets($command);

        $itemsByStore = [];
        foreach ($targets as $store) {
            if ($store->id === null) {
                continue;
            }
            $client = $this->clientFactory->forStore($store);
            $items = [];
            foreach ($client->fetchAllProducts() as $product) {
                $items[] = $product;
                if ($product->type === 'variable') {
                    foreach ($client->fetchAllVariationsFor($product->wcProductId) as $variation) {
                        $items[] = $variation;
                    }
                }
            }
            $this->products->replaceForStore($store->id, $items);
            $itemsByStore[$store->name] = count($items);
        }

        return new PullWooStoreResult($itemsByStore);
    }

    /**
     * @return list<WooCommerceStore>
     */
    private function resolveTargets(PullWooStore $command): array
    {
        if ($command->storeName === null) {
            return $this->stores->findAll();
        }
        $store = $this->stores->findByName($command->storeName);
        if ($store === null) {
            throw WooCommerceStoreNotFoundException::forName($command->storeName);
        }

        return [$store];
    }
}
