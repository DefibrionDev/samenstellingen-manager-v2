<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;

/**
 * Bouwt een tweedeling-resultaat voor de WooCommerce-index:
 *
 *  - rows: per managed-AFAS-itemcode één rij, met per geregistreerde store
 *    een {@see WooIndexCell} of null (niet gepubliceerd op die store).
 *  - orphans: WC-producten die niet matchen op onze managed-set — ofwel
 *    omdat ze geen AFAS-meta hebben, ofwel omdat hun meta naar een
 *    itemcode wijst dat wij niet beheren (taal-siblings, archief, …).
 *
 * Zie PLAN.md §7 (slice WC-3).
 */
final readonly class ListWooIndexHandler
{
    public function __construct(
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
        private GroupRepository $groups,
        private WooCommerceStoreRepository $stores,
        private WooProductRepository $products,
    ) {
    }

    public function __invoke(ListWooIndex $command): WooIndexResult
    {
        $managed = $this->managedItemcodes();
        $targetStores = $this->resolveStores($command->storeName);

        // store_id → list<WC product/variation>
        $productsByStore = [];
        foreach ($targetStores as $store) {
            if ($store->id === null) {
                continue;
            }
            $productsByStore[$store->id] = $this->products->findAllForStore($store->id);
        }

        $rows = $this->buildRows($managed, $targetStores, $productsByStore, $command->missingOnly);
        $orphans = $this->buildOrphans($managed, $targetStores, $productsByStore);

        return new WooIndexResult($rows, $orphans);
    }

    /**
     * @return array<string, true>  Map afasItemcode → true
     */
    private function managedItemcodes(): array
    {
        $managed = [];
        foreach ($this->groups->findAll() as $group) {
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->afasItemcode !== null) {
                    $managed[$base->afasItemcode] = true;
                }
                if ($base->id === null) {
                    continue;
                }
                foreach ($this->variants->findMatchedAfasItemcodesForBase($base->id) as $itemcode) {
                    $managed[$itemcode] = true;
                }
            }
        }

        return $managed;
    }

    /**
     * @return list<WooCommerceStore>
     */
    private function resolveStores(?string $storeName): array
    {
        if ($storeName === null) {
            return $this->stores->findAll();
        }
        $store = $this->stores->findByName($storeName);
        if ($store === null) {
            throw WooCommerceStoreNotFoundException::forName($storeName);
        }

        return [$store];
    }

    /**
     * @param array<string, true>                                   $managed
     * @param list<WooCommerceStore>                                $targetStores
     * @param array<int, list<WooProduct|WooProductVariation>>      $productsByStore
     *
     * @return list<WooIndexRow>
     */
    private function buildRows(array $managed, array $targetStores, array $productsByStore, bool $missingOnly): array
    {
        // store_id → afasItemcode → cell (eerste match)
        $cellsByStoreByItemcode = [];
        foreach ($productsByStore as $storeId => $items) {
            foreach ($items as $item) {
                if ($item->afasItemcode === null || !isset($managed[$item->afasItemcode])) {
                    continue;
                }
                $cellsByStoreByItemcode[$storeId][$item->afasItemcode] ??= new WooIndexCell(
                    wcProductId: $item->wcProductId,
                    wcType: $item instanceof WooProductVariation ? 'variation' : $item->type,
                    sku: $item->sku,
                    name: $item->name,
                    status: $item->status,
                    permalink: $item->permalink,
                );
            }
        }

        $rows = [];
        $itemcodes = array_map('strval', array_keys($managed));
        sort($itemcodes, SORT_STRING);

        foreach ($itemcodes as $itemcode) {
            $cells = [];
            $hasNull = false;
            foreach ($targetStores as $store) {
                if ($store->id === null) {
                    continue;
                }
                $cell = $cellsByStoreByItemcode[$store->id][$itemcode] ?? null;
                $cells[$store->id] = $cell;
                if ($cell === null) {
                    $hasNull = true;
                }
            }
            if ($missingOnly && !$hasNull) {
                continue;
            }
            $rows[] = new WooIndexRow($itemcode, null, $cells);
        }

        return $rows;
    }

    /**
     * @param array<string, true>                              $managed
     * @param list<WooCommerceStore>                           $targetStores
     * @param array<int, list<WooProduct|WooProductVariation>> $productsByStore
     *
     * @return list<WooOrphanRow>
     */
    private function buildOrphans(array $managed, array $targetStores, array $productsByStore): array
    {
        $storesById = [];
        foreach ($targetStores as $store) {
            if ($store->id !== null) {
                $storesById[$store->id] = $store;
            }
        }

        $orphans = [];
        foreach ($productsByStore as $storeId => $items) {
            $store = $storesById[$storeId] ?? null;
            if ($store === null) {
                continue;
            }
            foreach ($items as $item) {
                if ($item->afasItemcode !== null && isset($managed[$item->afasItemcode])) {
                    continue;
                }
                $orphans[] = new WooOrphanRow(
                    storeId: $storeId,
                    storeName: $store->name,
                    wcProductId: $item->wcProductId,
                    wcType: $item instanceof WooProductVariation ? 'variation' : $item->type,
                    sku: $item->sku,
                    name: $item->name,
                    status: $item->status,
                    afasItemcode: $item->afasItemcode,
                    permalink: $item->permalink,
                );
            }
        }

        return $orphans;
    }
}
