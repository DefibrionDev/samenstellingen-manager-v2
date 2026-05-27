<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

/**
 * Controleert per (groep × base × gekoppelde accessoire × prijslijst) of de
 * AFAS-prijs van de variant overeenkomt met de canonieke toeslag
 * (accessoires.delta_cents). Twee soorten drift in één rapport:
 *
 * - toeslag-drift: variant-prijs bestaat, maar (variant - base) != expected delta.
 * - missing: variant-prijs ontbreekt in een prijslijst waar de base wél prijs heeft.
 *
 * Verwachte variant-SKU: `base.afas_itemcode + '-' + accessoire.itemcode`.
 * Alleen prijslijst-prijzen (debiteur_id IS NULL). Klant-prijzen later.
 */
final readonly class PriceAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupAccessoireRepository $links,
        private AfasPrijsRepository $prijzen,
        private AfasPrijslijstRepository $prijslijsten,
        private PrijslijstBlacklistRepository $blacklist,
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
        $blacklist = [];
        foreach ($this->blacklist->findAll() as $entry) {
            $blacklist[$entry->prijslijstId] = true;
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

                $basePrijzenPerPrijslijst = $this->indexLatestPerPrijslijst(
                    $this->prijzen->findByItemcode($base->afasItemcode),
                );
                if ($basePrijzenPerPrijslijst === []) {
                    continue;
                }

                foreach ($accessoires as $accessoire) {
                    $variantSku = $base->afasItemcode . '-' . $accessoire->itemcode;
                    $variantPrijzenPerPrijslijst = $this->indexLatestPerPrijslijst(
                        $this->prijzen->findByItemcode($variantSku),
                    );

                    foreach ($basePrijzenPerPrijslijst as $prijslijstId => $basePrijs) {
                        if (isset($blacklist[(string) $prijslijstId])) {
                            continue;
                        }
                        $variantPrijs = $variantPrijzenPerPrijslijst[$prijslijstId] ?? null;
                        if ($variantPrijs === null) {
                            $rows[] = new PriceDriftRow(
                                groupName: $group->name,
                                baseAfasItemcode: $base->afasItemcode,
                                baseName: $base->name,
                                variantAfasItemcode: $variantSku,
                                accessoireItemcode: $accessoire->itemcode,
                                accessoireLabel: $accessoire->label,
                                expectedDeltaCents: $accessoire->deltaCents,
                                prijslijstId: (string) $prijslijstId,
                                prijslijstOmschrijving: $prijslijstNamen[(string) $prijslijstId] ?? null,
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
                                prijslijstId: (string) $prijslijstId,
                                prijslijstOmschrijving: $prijslijstNamen[(string) $prijslijstId] ?? null,
                                basePrijsCents: $basePrijs->verkoopprijsCents,
                                variantPrijsCents: $variantPrijs->verkoopprijsCents,
                                actualDeltaCents: $actualDelta,
                                status: 'toeslag-drift',
                            );
                        }
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Filter op prijslijst-prijzen (debiteur_id IS NULL) en pak per prijslijst
     * de meest recente (`geldig_van` lexicaal grootste) — Get_Prijzen kan meerdere
     * actieve overlappende rijen leveren, maar de meest recente is de geldende.
     *
     * @param list<AfasPrijs> $prijzen
     * @return array<string, AfasPrijs>
     */
    private function indexLatestPerPrijslijst(array $prijzen): array
    {
        $latest = [];
        foreach ($prijzen as $p) {
            if ($p->debiteurId !== null) {
                continue;
            }
            if ($p->staffelAantal !== null && $p->staffelAantal > 1) {
                // Hogere staffels overslaan in audit — alleen baseline-prijs vergelijken.
                continue;
            }
            $current = $latest[$p->prijslijstId] ?? null;
            if ($current === null || $p->geldigVan > $current->geldigVan) {
                $latest[$p->prijslijstId] = $p;
            }
        }

        return $latest;
    }
}
