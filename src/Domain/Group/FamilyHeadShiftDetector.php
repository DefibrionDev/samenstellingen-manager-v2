<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;

/**
 * Detecteert per groep of Itemcode_Parent in AFAS verschoven is naar een
 * nieuwe gemeenschappelijke waarde. Pure functie — geen side-effects.
 *
 * Sanity rails (zie PLAN.md §23):
 *  - Minstens 3 bases met SKU moeten dezelfde nieuwe parent hebben.
 *  - De nieuwe parent moet zelf bestaan in afas_samenstellingen.
 *  - De nieuwe parent mag geen family-head van een andere group zijn.
 *  - Bases zonder afas_itemcode tellen niet mee.
 */
final readonly class FamilyHeadShiftDetector
{
    private const MIN_BASES_THRESHOLD = 3;

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
            $candidateCounts = [];
            foreach ($bases as $base) {
                $sku = $base->afasItemcode;
                if ($sku === null || !array_key_exists($sku, $parentByItemcode)) {
                    continue;
                }
                $currentParent = $parentByItemcode[$sku];
                if ($currentParent === null || $currentParent === $group->familyHeadItemcode) {
                    continue;
                }
                $candidateCounts[$currentParent] = ($candidateCounts[$currentParent] ?? 0) + 1;
            }

            foreach ($candidateCounts as $newHead => $count) {
                if ($count < self::MIN_BASES_THRESHOLD) {
                    continue;
                }
                if (!isset($existingItemcodes[(string) $newHead])) {
                    continue;
                }
                if (isset($claimedHeads[(string) $newHead])) {
                    continue;
                }
                $shifts[] = new FamilyHeadShift(
                    $group->familyHeadItemcode,
                    (string) $newHead,
                    $count,
                );
                break;
            }
        }

        return $shifts;
    }
}
