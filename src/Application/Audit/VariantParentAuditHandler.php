<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

/**
 * Detecteert matched accessoire-variants waar AFAS' `Itemcode_Parent` niet
 * naar de family-head van hun groep wijst. Skipt variants die afwezig zijn
 * in de AFAS-snapshot of die *zelf* de family-head zijn (slice 52 dekt die).
 * Zie PLAN-AFAS.md §34.
 */
final readonly class VariantParentAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
        private AfasSamenstellingenRepository $afasSamenstellingen,
    ) {
    }

    /**
     * @return list<VariantParentDriftRow>
     */
    public function __invoke(AuditVariantParent $command): array
    {
        $rows = [];
        $seen = [];
        foreach ($this->groups->findAll() as $group) {
            $head = $group->familyHeadItemcode;
            foreach ($this->bases->findAllForGroup($head) as $base) {
                if ($base->id === null) {
                    continue;
                }
                foreach ($this->variants->findMatchedAfasItemcodesForBase($base->id) as $itemcode) {
                    if ($itemcode === $head) {
                        continue; // slice 52 dekt de head zelf
                    }
                    if (isset($seen[$itemcode])) {
                        continue;
                    }
                    $seen[$itemcode] = true;
                    $samenstelling = $this->afasSamenstellingen->findByItemcode($itemcode);
                    if ($samenstelling === null) {
                        continue;
                    }
                    if ($samenstelling->itemcodeParent === $head) {
                        continue;
                    }
                    $rows[] = new VariantParentDriftRow(
                        afasItemcode: $itemcode,
                        currentParent: $samenstelling->itemcodeParent,
                        expectedParent: $head,
                        groupName: $group->name,
                    );
                }
            }
        }
        usort($rows, static function (VariantParentDriftRow $a, VariantParentDriftRow $b): int {
            return $a->expectedParent <=> $b->expectedParent ?: strcmp($a->afasItemcode, $b->afasItemcode);
        });

        return $rows;
    }
}
