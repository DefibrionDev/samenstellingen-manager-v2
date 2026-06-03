<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;

/**
 * Synchroniseer publicatie-state (per base × website) naar AFAS. Voor elke
 * base bouwen we een flag-map (per website: Sync + Tonen op true/false) en
 * PUT'en die op de base zelf én op elk `<base>-<accessoire>`-itemcode dat
 * via de aan de groep gelinkte accessoires intent-based wordt afgeleid en
 * effectief in `afas_samenstellingen` bestaat. `AfasFreeFieldStateReader`
 * skipt no-ops: alleen PUT als de huidige AFAS-state afwijkt van desired.
 * Zie PLAN.md §25 en §30.
 */
final readonly class SyncPublicationsHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupAccessoireRepository $accessoires,
        private AfasSamenstellingenRepository $afasSamenstellingen,
        private WebsiteRepository $websites,
        private BasePublicationRepository $publications,
        private PublicationSyncWriter $writer,
        private AfasFreeFieldStateReader $afasState,
    ) {
    }

    public function __invoke(SyncPublications $command): SyncPublicationsResult
    {
        $allWebsites = $this->websites->findAll();
        if ($allWebsites === []) {
            return new SyncPublicationsResult([], 0, []);
        }

        $afasFlagState = $this->afasState->readAll();

        $plans = [];
        $noopSkipped = 0;
        $totalCandidates = 0;
        foreach ($this->groups->findAll() as $group) {
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->id === null || $base->afasItemcode === null) {
                    continue;
                }
                $publishedWebsiteIds = [];
                foreach ($this->publications->findAllForBase($base->id) as $pub) {
                    if ($pub->published) {
                        $publishedWebsiteIds[$pub->websiteId] = true;
                    }
                }
                $flags = [];
                foreach ($allWebsites as $website) {
                    $isPublished = $website->id !== null && isset($publishedWebsiteIds[$website->id]);
                    $flags[$website->ffSyncUuid] = $isPublished;
                    $flags[$website->ffTonenUuid] = $isPublished;
                }

                $targetItemcodes = $this->collectTargetItemcodes($group->familyHeadItemcode, $base->afasItemcode);
                foreach ($targetItemcodes as $itemcode) {
                    ++$totalCandidates;
                    // No-op skip: als AFAS al ALLE bekende flags op de gewenste waarde
                    // heeft staan, slaan we over (geen PUT). Onbekende flags →
                    // veiligheid voorrang → wel PUT'en.
                    if ($this->matchesCurrentState($itemcode, $flags, $afasFlagState)) {
                        ++$noopSkipped;
                        continue;
                    }
                    $plans[] = new PublicationSyncPlan($itemcode, $base->afasItemcode, $flags);
                    if ($command->limit !== null && count($plans) >= $command->limit) {
                        break 3;
                    }
                }
            }
        }

        $applied = 0;
        $failures = [];
        if ($command->apply) {
            foreach ($plans as $plan) {
                try {
                    $this->writer->apply($plan);
                    ++$applied;
                } catch (PublicationSyncFailedException $e) {
                    $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
                }
            }
        }

        return new SyncPublicationsResult($plans, $applied, $failures, $noopSkipped, $totalCandidates);
    }

    /**
     * Returnt true wanneer ALLE gewenste flags al de juiste waarde hebben in
     * AFAS. Een onbekende flag (niet in de reader-output) betekent "we weten
     * het niet" → return false (= wel PUT'en, veilig).
     *
     * @param array<string, bool>                  $desired
     * @param array<string, array<string, bool>>   $currentState  per itemcode → uuid → bool
     */
    private function matchesCurrentState(string $itemcode, array $desired, array $currentState): bool
    {
        $current = $currentState[$itemcode] ?? null;
        if ($current === null) {
            return false;
        }
        foreach ($desired as $uuid => $expected) {
            if (!array_key_exists($uuid, $current) || $current[$uuid] !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Intent-based target-derivation: base zelf + `<base>-<accessoire>`-itemcodes
     * voor elke accessoire die aan de groep gelinkt is, mits dat afgeleide
     * itemcode effectief in `afas_samenstellingen` bestaat. Taal-siblings
     * (`10144-CZ`, `10144-DE`-bases zonder eigen DB-entry) komen hier niet
     * tussendoor omdat onze intent die strings nooit produceert; bucket-A items
     * (variants waarvoor auto-sync's BOM-match faalt maar AFAS het itemcode wel
     * kent) worden wél meegenomen.
     *
     * @return list<string>
     */
    private function collectTargetItemcodes(string $familyHeadItemcode, string $baseAfasItemcode): array
    {
        $codes = [$baseAfasItemcode];
        foreach ($this->accessoires->findAllForGroup($familyHeadItemcode) as $accessoire) {
            $expected = $baseAfasItemcode . '-' . $accessoire->itemcode;
            if ($this->afasSamenstellingen->findByItemcode($expected) !== null) {
                $codes[] = $expected;
            }
        }
        $result = array_values(array_unique($codes));
        sort($result, SORT_STRING);

        return $result;
    }
}
