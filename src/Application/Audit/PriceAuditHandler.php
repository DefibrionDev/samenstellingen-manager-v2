<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

/**
 * Controleert per (groep × base × gekoppelde accessoire × prijslijst × staffel) of
 * de AFAS-prijs van de variant overeenkomt met `base + accessoires.delta_cents`.
 * Drie statuscategorieën:
 *
 * - toeslag-drift: variant-prijs bestaat in deze staffel, maar (variant - base) != expected delta.
 * - missing: base heeft deze staffel, variant niet.
 * - inconsistent-staffel: variant heeft een staffel die base niet heeft (auto-fix onveilig).
 *
 * Verwachte variant-SKU: `base.afas_itemcode + '-' + accessoire.itemcode`.
 * Alleen prijslijst-prijzen (debiteur_id IS NULL). Klant-prijzen later.
 * Baseline-prijzen (Hoeveelheid=0) en hogere staffels worden afzonderlijk
 * geaudit — toeslag is plat: zelfde delta op elke staffel.
 */
final readonly class PriceAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupAccessoireRepository $links,
        private AfasPrijsRepository $prijzen,
        private AfasPrijslijstRepository $prijslijsten,
        private PrijslijstWhitelistRepository $whitelist,
    ) {
    }

    /**
     * @return list<PriceDriftRow>
     */
    public function __invoke(AuditPrices $command): array
    {
        $prijslijstNamen = [];
        foreach ($this->prijslijsten->findAll() as $p) {
            $prijslijstNamen[$p->id] = $p->omschrijving;
        }
        $whitelist = [];
        foreach ($this->whitelist->findAll() as $entry) {
            $whitelist[$entry->prijslijstId] = true;
        }

        $rows = [];
        foreach ($this->groups->findAll() as $group) {
            $accessoires = $this->links->findAllForGroup($group->familyHeadItemcode);
            if ($accessoires === []) {
                continue;
            }

            $bases = $this->bases->findAllForGroup($group->familyHeadItemcode);
            foreach ($bases as $base) {
                if ($base->afasItemcode === null) {
                    continue;
                }

                $baseIndex = $this->indexLatestPerPrijslijstAndStaffel(
                    $this->prijzen->findByItemcode($base->afasItemcode),
                );
                if ($baseIndex === []) {
                    continue;
                }

                foreach ($accessoires as $accessoire) {
                    $variantSku = $base->afasItemcode . '-' . $accessoire->itemcode;
                    $variantIndex = $this->indexLatestPerPrijslijstAndStaffel(
                        $this->prijzen->findByItemcode($variantSku),
                    );

                    // Drift + missing: per (prijslijst, staffel) waar base prijs heeft.
                    foreach ($baseIndex as $key => $basePrijs) {
                        if (!isset($whitelist[$basePrijs->prijslijstId])) {
                            continue;
                        }
                        $variantPrijs = $variantIndex[$key] ?? null;
                        if ($variantPrijs === null) {
                            $rows[] = new PriceDriftRow(
                                groupName: $group->name,
                                baseAfasItemcode: $base->afasItemcode,
                                baseName: $base->name,
                                variantAfasItemcode: $variantSku,
                                accessoireItemcode: $accessoire->itemcode,
                                accessoireLabel: $accessoire->label,
                                expectedDeltaCents: $accessoire->deltaCents,
                                prijslijstId: $basePrijs->prijslijstId,
                                prijslijstOmschrijving: $prijslijstNamen[$basePrijs->prijslijstId] ?? null,
                                staffelAantal: $basePrijs->staffelAantal,
                                basePrijsCents: $basePrijs->verkoopprijsCents,
                                variantPrijsCents: null,
                                actualDeltaCents: null,
                                status: 'missing',
                            );
                            continue;
                        }

                        $actualDelta = $variantPrijs->verkoopprijsCents - $basePrijs->verkoopprijsCents;
                        if ($actualDelta !== $accessoire->deltaCents) {
                            $rows[] = new PriceDriftRow(
                                groupName: $group->name,
                                baseAfasItemcode: $base->afasItemcode,
                                baseName: $base->name,
                                variantAfasItemcode: $variantSku,
                                accessoireItemcode: $accessoire->itemcode,
                                accessoireLabel: $accessoire->label,
                                expectedDeltaCents: $accessoire->deltaCents,
                                prijslijstId: $basePrijs->prijslijstId,
                                prijslijstOmschrijving: $prijslijstNamen[$basePrijs->prijslijstId] ?? null,
                                staffelAantal: $basePrijs->staffelAantal,
                                basePrijsCents: $basePrijs->verkoopprijsCents,
                                variantPrijsCents: $variantPrijs->verkoopprijsCents,
                                actualDeltaCents: $actualDelta,
                                status: 'toeslag-drift',
                            );
                        }
                    }

                    // Inconsistent-staffel: variant-staffels die base niet heeft.
                    foreach ($variantIndex as $key => $variantPrijs) {
                        if (isset($baseIndex[$key])) {
                            continue;
                        }
                        if (!isset($whitelist[$variantPrijs->prijslijstId])) {
                            continue;
                        }
                        $rows[] = new PriceDriftRow(
                            groupName: $group->name,
                            baseAfasItemcode: $base->afasItemcode,
                            baseName: $base->name,
                            variantAfasItemcode: $variantSku,
                            accessoireItemcode: $accessoire->itemcode,
                            accessoireLabel: $accessoire->label,
                            expectedDeltaCents: $accessoire->deltaCents,
                            prijslijstId: $variantPrijs->prijslijstId,
                            prijslijstOmschrijving: $prijslijstNamen[$variantPrijs->prijslijstId] ?? null,
                            staffelAantal: $variantPrijs->staffelAantal,
                            basePrijsCents: null,
                            variantPrijsCents: $variantPrijs->verkoopprijsCents,
                            actualDeltaCents: null,
                            status: 'inconsistent-staffel',
                        );
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Index op `<prijslijst>|<staffel>` met staffel=0 voor baseline (null in DB).
     * Bij meerdere actieve rijen voor dezelfde (lijst, staffel): pak de meest recente
     * geldig_van. Klant-prijzen (debiteur_id != null) worden geskipped.
     *
     * @param list<AfasPrijs> $prijzen
     * @return array<string, AfasPrijs>
     */
    private function indexLatestPerPrijslijstAndStaffel(array $prijzen): array
    {
        $latest = [];
        foreach ($prijzen as $p) {
            if ($p->debiteurId !== null) {
                continue;
            }
            $staffelKey = $p->staffelAantal ?? 0;
            $key = $p->prijslijstId . '|' . $staffelKey;
            $current = $latest[$key] ?? null;
            if ($current === null || $p->geldigVan > $current->geldigVan) {
                $latest[$key] = $p;
            }
        }

        return $latest;
    }
}
