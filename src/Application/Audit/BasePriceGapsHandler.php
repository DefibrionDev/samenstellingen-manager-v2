<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstWhitelistRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

/**
 * Vindt managed base-samenstellingen die helemaal géén prijs hebben in een
 * whitelist-prijslijst — het gat dat `PriceAuditHandler` niet ziet (die vergelijkt
 * alleen een variant tegen een base die al in een lijst staat). Per (base ×
 * whitelist-prijslijst) waar geen prijslijst-prijs (`debiteur_id IS NULL`) bestaat
 * komt één rij. Klant-prijzen tellen niet als dekking; bases zonder afas_itemcode
 * worden overgeslagen. Read-only — muteert niets.
 */
final readonly class BasePriceGapsHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private AfasPrijsRepository $prijzen,
        private AfasPrijslijstRepository $prijslijsten,
        private PrijslijstWhitelistRepository $whitelist,
    ) {
    }

    /**
     * @return list<BasePriceGapRow>
     */
    public function __invoke(ListBasePriceGaps $command): array
    {
        $prijslijstNamen = [];
        foreach ($this->prijslijsten->findAll() as $p) {
            $prijslijstNamen[$p->id] = $p->omschrijving;
        }

        $whitelisted = [];
        foreach ($this->whitelist->findAll() as $entry) {
            $whitelisted[] = $entry->prijslijstId;
        }
        sort($whitelisted);

        $rows = [];
        foreach ($this->groups->findAll() as $group) {
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->afasItemcode === null || $base->afasItemcode === '') {
                    continue;
                }

                $covered = $this->coveredPrijslijsten($base->afasItemcode);
                foreach ($whitelisted as $prijslijstId) {
                    if (isset($covered[$prijslijstId])) {
                        continue;
                    }
                    $rows[] = new BasePriceGapRow(
                        prijslijstId: $prijslijstId,
                        prijslijstOmschrijving: $prijslijstNamen[$prijslijstId] ?? null,
                        baseAfasItemcode: $base->afasItemcode,
                        groupName: $group->name,
                        baseName: $base->name,
                    );
                }
            }
        }

        return $rows;
    }

    /**
     * Prijslijst-ids waarvoor deze base minstens één prijslijst-prijs heeft
     * (debiteur_id IS NULL). Klant-prijzen tellen niet mee.
     *
     * @return array<string, true>
     */
    private function coveredPrijslijsten(string $itemcode): array
    {
        $covered = [];
        foreach ($this->prijzen->findByItemcode($itemcode) as $prijs) {
            if ($prijs->debiteurId !== null) {
                continue;
            }
            $covered[$prijs->prijslijstId] = true;
        }

        return $covered;
    }
}
