<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;

/**
 * Detecteert AFAS-samenstellingen met identieke BOM (gesorteerde, kommagescheiden
 * component_itemcodes). Iedere groep met ≥ 2 leden is verdacht — vaak een variant
 * waarvan het accessoire-itemcode in AFAS niet aan de BOM is toegevoegd, waardoor
 * de variant-BOM gelijk is aan de pure base.
 *
 * Read-only: rapporteert, fixt niet. Audit raakt alle samenstellingen, niet alleen
 * die in onze groepen-tabel — dit is een AFAS-data-kwaliteit-check.
 */
final readonly class DuplicateBomAuditHandler
{
    public function __construct(private AfasSamenstellingenRepository $samenstellingen)
    {
    }

    /**
     * @return list<DuplicateBomGroup>
     */
    public function __invoke(AuditDuplicateBoms $command): array
    {
        $byFingerprint = [];
        foreach ($this->samenstellingen->findAll() as $s) {
            if ($s->bomItemcodes === []) {
                continue;
            }
            $fp = $s->bomKey();
            $byFingerprint[$fp][] = ['itemcode' => $s->itemcode, 'name' => $s->name];
        }

        $groups = [];
        foreach ($byFingerprint as $fp => $members) {
            if (count($members) < 2) {
                continue;
            }
            $groups[] = new DuplicateBomGroup($fp, $members);
        }

        // Sorteer descending op aantal leden, dan op fingerprint voor stabiele output.
        usort(
            $groups,
            static fn (DuplicateBomGroup $a, DuplicateBomGroup $b): int
                => count($b->members) <=> count($a->members) ?: strcmp($a->fingerprint, $b->fingerprint),
        );

        return $groups;
    }
}
