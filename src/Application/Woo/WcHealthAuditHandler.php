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
 * Health-check: per managed AFAS-itemcode bekijken in welke shop 'ie staat
 * en of het correcte WC-type gebruikt wordt. Family-heads moeten `variable`
 * zijn; non-head bases + matched accessoire-variants moeten `variation`
 * zijn. Mismatches (`simple` waar `variation` verwacht) worden geflagd als
 * `WrongType`. Zie PLAN.md §10.
 */
final readonly class WcHealthAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
        private WooCommerceStoreRepository $stores,
        private WooProductRepository $products,
    ) {
    }

    /**
     * @return list<WcHealthRow>
     */
    public function __invoke(AuditWcHealth $command): array
    {
        $targetStores = $this->resolveStores($command->storeName);
        $expectedByCode = $this->buildExpectedTypes();

        // Pre-load products per store, gegroepeerd op afas_itemcode.
        $productsByStoreByCode = [];
        foreach ($targetStores as $store) {
            if ($store->id === null) {
                continue;
            }
            $productsByStoreByCode[$store->id] = [];
            foreach ($this->products->findAllForStore($store->id) as $item) {
                if ($item->afasItemcode === null) {
                    continue;
                }
                $productsByStoreByCode[$store->id][$item->afasItemcode][] = $item;
            }
        }

        $rows = [];
        $codes = array_map('strval', array_keys($expectedByCode));
        sort($codes, SORT_STRING);
        foreach ($codes as $code) {
            $expectedType = $expectedByCode[$code];
            $cells = [];
            foreach ($targetStores as $store) {
                if ($store->id === null) {
                    continue;
                }
                $items = $productsByStoreByCode[$store->id][$code] ?? [];
                $cells[$store->id] = $this->cellFor($items, $expectedType);
            }
            $rows[] = new WcHealthRow($code, $expectedType, $cells);
        }

        return $rows;
    }

    /**
     * @return array<string, 'variable'|'variation'>
     */
    private function buildExpectedTypes(): array
    {
        $expected = [];
        foreach ($this->groups->findAll() as $group) {
            $head = $group->familyHeadItemcode;
            foreach ($this->bases->findAllForGroup($head) as $base) {
                if ($base->afasItemcode !== null) {
                    $expected[$base->afasItemcode] = $base->afasItemcode === $head ? 'variable' : 'variation';
                }
                if ($base->id === null) {
                    continue;
                }
                foreach ($this->variants->findMatchedAfasItemcodesForBase($base->id) as $itemcode) {
                    // Matched-variant: family-head is altijd variable, accessoire-variants altijd variation.
                    if (!isset($expected[$itemcode])) {
                        $expected[$itemcode] = $itemcode === $head ? 'variable' : 'variation';
                    }
                }
            }
        }

        return $expected;
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
     * @param list<WooProduct|WooProductVariation> $items
     */
    private function cellFor(array $items, string $expectedType): WcHealthCell
    {
        if ($items === []) {
            return new WcHealthCell(null, null, null, WcHealthStatus::Missing);
        }
        // Prioriteer een match op verwachte type voor de cel-keuze; anders eerste hit.
        $best = $items[0];
        foreach ($items as $item) {
            $actualType = $item instanceof WooProductVariation ? 'variation' : $item->type;
            if ($actualType === $expectedType) {
                $best = $item;
                break;
            }
        }
        $actualType = $best instanceof WooProductVariation ? 'variation' : $best->type;
        if ($actualType !== $expectedType) {
            return new WcHealthCell($best->wcProductId, $actualType, $best->status, WcHealthStatus::WrongType);
        }
        if ($best->status !== 'publish') {
            return new WcHealthCell($best->wcProductId, $actualType, $best->status, WcHealthStatus::NotPublish);
        }

        return new WcHealthCell($best->wcProductId, $actualType, $best->status, WcHealthStatus::Ok);
    }
}
