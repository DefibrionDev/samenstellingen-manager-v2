<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

final class DuplicateDetector
{
    /**
     * Groepeer samenstellingen op hun BOM-set en markeer per identieke BOM de duplicates.
     * De laagste itemcode (alfabetisch via natsort) blijft canonical; de rest krijgt
     * `duplicateOfItemcode` = canonical itemcode.
     *
     * Lege BOMs worden niet gededupliceerd (dat zou alles met lege BOM tot één canonical maken).
     *
     * @param list<AfasSamenstelling> $samenstellingen
     *
     * @return list<AfasSamenstelling>
     */
    public function annotate(array $samenstellingen): array
    {
        /** @var array<string, list<AfasSamenstelling>> $byBomKey */
        $byBomKey = [];
        foreach ($samenstellingen as $samenstelling) {
            if ($samenstelling->bomItemcodes === []) {
                continue;
            }
            $byBomKey[$samenstelling->bomKey()][] = $samenstelling;
        }

        /** @var array<string, string> $duplicateOf itemcode → canonical itemcode */
        $duplicateOf = [];
        foreach ($byBomKey as $group) {
            if (count($group) < 2) {
                continue;
            }
            $itemcodes = array_map(static fn (AfasSamenstelling $s): string => $s->itemcode, $group);
            natsort($itemcodes);
            $canonical = (string) reset($itemcodes);
            foreach ($itemcodes as $itemcode) {
                if ($itemcode !== $canonical) {
                    $duplicateOf[$itemcode] = $canonical;
                }
            }
        }

        $result = [];
        foreach ($samenstellingen as $samenstelling) {
            $duplicateOfItemcode = $duplicateOf[$samenstelling->itemcode] ?? null;
            if ($duplicateOfItemcode === $samenstelling->duplicateOfItemcode) {
                $result[] = $samenstelling;
                continue;
            }
            $result[] = new AfasSamenstelling(
                $samenstelling->itemcode,
                $samenstelling->name,
                $samenstelling->itemcodeParent,
                $samenstelling->bomItemcodes,
                $duplicateOfItemcode,
            );
        }

        return $result;
    }
}
