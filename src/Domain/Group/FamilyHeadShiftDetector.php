<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;

/**
 * Detecteert per groep of Itemcode_Parent in AFAS verschoven is naar een
 * nieuwe gemeenschappelijke waarde. Pure functie — geen side-effects.
 *
 * Sanity rails (zie PLAN.md §23):
 *  - ALLE SKU-bases met een ingevulde parent in AFAS moeten unanimous
 *    naar dezelfde nieuwe parent wijzen (geen drempel — 1 base die nog
 *    op de oude parent staat blokkeert de shift).
 *  - Bases met `itemcode_parent IS NULL` tellen niet mee (geen vote).
 *  - Minstens 1 vote nodig — een groep zonder data verschuift niet.
 *  - De nieuwe parent moet zelf bestaan in afas_samenstellingen.
 *  - De nieuwe parent mag geen family-head van een andere group zijn.
 *  - Bases zonder afas_itemcode tellen niet mee.
 */
final readonly class FamilyHeadShiftDetector
{
    /**
     * @param list<Group>                                $groups
     * @param array<int|string, iterable<GroupBase>>     $basesByFamilyHead Caller mapt familyHead → bases. PHP cast numerieke string-keys naar int — daarom int|string.
     * @param list<AfasSamenstelling>                    $afasSamenstellingen
     *
     * @return list<FamilyHeadShift>
     */
    public function detect(array $groups, array $basesByFamilyHead, array $afasSamenstellingen): array
    {
        $parentByItemcode = [];
        $existingItemcodes = [];
        foreach ($afasSamenstellingen as $s) {
            $parentByItemcode[$s->itemcode] = $s->itemcodeParent;
            $existingItemcodes[$s->itemcode] = true;
        }
        $claimedHeads = [];
        foreach ($groups as $g) {
            $claimedHeads[$g->familyHeadItemcode] = true;
        }

        $shifts = [];
        foreach ($groups as $group) {
            $bases = $basesByFamilyHead[$group->familyHeadItemcode] ?? [];
            $stayingOnCurrent = false;
            $newHeadCounts = [];
            foreach ($bases as $base) {
                $sku = $base->afasItemcode;
                if ($sku === null || !array_key_exists($sku, $parentByItemcode)) {
                    continue;
                }
                $currentParent = $parentByItemcode[$sku];
                if ($currentParent === null) {
                    continue;
                }
                if ($currentParent === $group->familyHeadItemcode) {
                    $stayingOnCurrent = true;
                    continue;
                }
                $newHeadCounts[$currentParent] = ($newHeadCounts[$currentParent] ?? 0) + 1;
            }

            // Unanimous: 1 distincte nieuwe parent, geen base nog op de oude.
            if ($newHeadCounts === [] || $stayingOnCurrent || count($newHeadCounts) > 1) {
                continue;
            }

            $newHead = (string) array_key_first($newHeadCounts);
            $count = $newHeadCounts[$newHead];

            if (!isset($existingItemcodes[$newHead])) {
                continue;
            }
            if (isset($claimedHeads[$newHead])) {
                continue;
            }

            $shifts[] = new FamilyHeadShift(
                $group->familyHeadItemcode,
                $newHead,
                $count,
            );
        }

        return $shifts;
    }
}
