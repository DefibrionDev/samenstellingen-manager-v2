<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

/**
 * Detecteert family-heads waarvan AFAS' `Itemcode_Parent` niet naar zichzelf
 * wijst. Defibrion-conventie: head.Itemcode_Parent = head.Itemcode (zodat
 * `WHERE Itemcode_Parent = X` ook de head zelf returnt). Family-heads die
 * niet in de AFAS-snapshot voorkomen worden geskipt — onze tool kan over
 * niet-aanwezige items niets zeggen. Zie PLAN-AFAS.md §32.
 */
final readonly class FamilyHeadParentAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private AfasSamenstellingenRepository $afasSamenstellingen,
    ) {
    }

    /**
     * @return list<FamilyHeadParentDriftRow>
     */
    public function __invoke(AuditFamilyHeadParent $command): array
    {
        $rows = [];
        foreach ($this->groups->findAll() as $group) {
            $head = $group->familyHeadItemcode;
            $samenstelling = $this->afasSamenstellingen->findByItemcode($head);
            if ($samenstelling === null) {
                continue;
            }
            $current = $samenstelling->itemcodeParent;
            if ($current === $head) {
                continue;
            }
            $rows[] = new FamilyHeadParentDriftRow($head, $current, $head, $group->name);
        }
        usort($rows, static fn (FamilyHeadParentDriftRow $a, FamilyHeadParentDriftRow $b) => strcmp($a->familyHead, $b->familyHead));

        return $rows;
    }
}
