<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

/**
 * Detecteert ontbrekende/afwijkende webshop-producttypes
 * (Product_type___01_/02_) op samenstellingen. De base-samenstelling is de bron
 * van waarheid; accessoire-varianten horen die exact over te nemen.
 *
 * Classificatie per samenstelling ("leeg" = 01 óf 02 leeg):
 *  - base met 01 óf 02 leeg            → BaseEmpty       (alleen in AFAS te vullen)
 *  - variant ≠ base, base gevuld       → VariantFixable  (auto-fixbaar)
 *  - variant ≠ base, base zelf leeg    → VariantBlocked  (eerst base vullen)
 *
 * Zie PLAN-AFAS.md §35.
 */
final readonly class ProductTypeAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
        private AfasSamenstellingenRepository $afasSamenstellingen,
    ) {
    }

    /**
     * @return list<ProductTypeIssueRow>
     */
    public function __invoke(AuditProductType $command): array
    {
        $rows = [];
        $seen = [];

        foreach ($this->groups->findAll() as $group) {
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->id === null || $base->afasItemcode === null) {
                    continue;
                }
                $baseItemcode = $base->afasItemcode;
                $baseSamenstelling = $this->afasSamenstellingen->findByItemcode($baseItemcode);
                if ($baseSamenstelling === null) {
                    continue; // base niet in snapshot — niet te auditen
                }

                $baseFilled = self::isFilled($baseSamenstelling);

                if (!isset($seen[$baseItemcode])) {
                    $seen[$baseItemcode] = true;
                    if (!$baseFilled) {
                        $rows[] = new ProductTypeIssueRow(
                            afasItemcode: $baseItemcode,
                            issueType: ProductTypeIssueType::BaseEmpty,
                            baseItemcode: $baseItemcode,
                            current01: $baseSamenstelling->productType01,
                            current02: $baseSamenstelling->productType02,
                            expected01: $baseSamenstelling->productType01,
                            expected02: $baseSamenstelling->productType02,
                            groupName: $group->name,
                        );
                    }
                }

                foreach ($this->variants->findMatchedAfasItemcodesForBase($base->id) as $itemcode) {
                    if ($itemcode === $baseItemcode || isset($seen[$itemcode])) {
                        continue;
                    }
                    $seen[$itemcode] = true;
                    $variant = $this->afasSamenstellingen->findByItemcode($itemcode);
                    if ($variant === null) {
                        continue;
                    }
                    if ($variant->productType01 === $baseSamenstelling->productType01
                        && $variant->productType02 === $baseSamenstelling->productType02) {
                        continue; // gelijk aan base → niets te doen
                    }

                    $rows[] = new ProductTypeIssueRow(
                        afasItemcode: $itemcode,
                        issueType: $baseFilled ? ProductTypeIssueType::VariantFixable : ProductTypeIssueType::VariantBlocked,
                        baseItemcode: $baseItemcode,
                        current01: $variant->productType01,
                        current02: $variant->productType02,
                        expected01: $baseSamenstelling->productType01,
                        expected02: $baseSamenstelling->productType02,
                        groupName: $group->name,
                    );
                }
            }
        }

        usort($rows, static fn (ProductTypeIssueRow $a, ProductTypeIssueRow $b): int => strcmp($a->afasItemcode, $b->afasItemcode));

        return $rows;
    }

    private static function isFilled(AfasSamenstelling $samenstelling): bool
    {
        return $samenstelling->productType01 !== null && $samenstelling->productType02 !== null;
    }
}
