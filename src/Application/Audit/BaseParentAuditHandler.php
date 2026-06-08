<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

/**
 * Detecteert non-head bases waar AFAS' `Itemcode_Parent` niet naar de
 * family-head van hun groep wijst. Defibrion-conventie: non-head-base
 * Itemcode_Parent = family_head. Skipt family-heads zelf (slice 52 dekt
 * die) en bases die niet in de AFAS-snapshot voorkomen. Zie PLAN-AFAS.md §33.
 */
final readonly class BaseParentAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private AfasSamenstellingenRepository $afasSamenstellingen,
    ) {
    }

    /**
     * @return list<BaseParentDriftRow>
     */
    public function __invoke(AuditBaseParent $command): array
    {
        $rows = [];
        foreach ($this->groups->findAll() as $group) {
            $head = $group->familyHeadItemcode;
            foreach ($this->bases->findAllForGroup($head) as $base) {
                if ($base->afasItemcode === null || $base->afasItemcode === $head) {
                    continue; // skip non-AFAS-linked bases en the family-head zelf
                }
                $samenstelling = $this->afasSamenstellingen->findByItemcode($base->afasItemcode);
                if ($samenstelling === null) {
                    continue;
                }
                if ($samenstelling->itemcodeParent === $head) {
                    continue;
                }
                $rows[] = new BaseParentDriftRow(
                    afasItemcode: $base->afasItemcode,
                    currentParent: $samenstelling->itemcodeParent,
                    expectedParent: $head,
                    groupName: $group->name,
                    languageCode: $base->languageCode ?? '',
                );
            }
        }
        usort($rows, static function (BaseParentDriftRow $a, BaseParentDriftRow $b): int {
            return $a->expectedParent <=> $b->expectedParent ?: strcmp($a->afasItemcode, $b->afasItemcode);
        });

        return $rows;
    }
}
