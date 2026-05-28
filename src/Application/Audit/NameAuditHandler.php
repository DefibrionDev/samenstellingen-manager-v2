<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Defibrion\Samenstellingen\Domain\Naming\UnknownLanguageException;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;

/**
 * Loopt over alle gematchte varianten (afas_status='matched'), berekent per rij
 * de verwachte naam volgens {@see VariantNamingPolicy} en vergelijkt strict met
 * de werkelijke AFAS-naam. Geeft een lijst drift-rijen terug.
 *
 * Read-only — geen rename, geen schrijfactie naar AFAS.
 */
final readonly class NameAuditHandler
{
    public function __construct(
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
        private AccessoireRepository $accessoires,
        private AfasSamenstellingenRepository $afas,
        private VariantNamingPolicy $policy,
    ) {
    }

    /**
     * @return list<NameDriftRow>
     */
    public function __invoke(AuditNames $command): array
    {
        $afasByItemcode = [];
        foreach ($this->afas->findAll() as $samenstelling) {
            $afasByItemcode[$samenstelling->itemcode] = $samenstelling;
        }

        $drift = [];
        foreach ($this->groups->findAll() as $group) {
            if ($group->modelNameNl === null) {
                continue; // Audit kan niet zonder model_name — sla over (zie /api/groups om te zien welke).
            }
            $basesById = [];
            foreach ($this->bases->findAllForGroup($group->familyHeadItemcode) as $base) {
                if ($base->id !== null) {
                    $basesById[$base->id] = $base;
                }
            }

            foreach ($this->variants->findAllForGroup($group->familyHeadItemcode) as $variant) {
                if ($variant->afasStatus !== 'matched' || $variant->afasSamenstellingItemcode === null) {
                    continue;
                }
                $base = $basesById[$variant->baseId] ?? null;
                if ($base === null) {
                    continue;
                }

                $accessoire = $variant->accessoireItemcode !== null
                    ? $this->accessoires->findByItemcode($variant->accessoireItemcode)
                    : null;

                try {
                    $expected = ($this->policy)->expectedName($group, $base, $accessoire);
                } catch (UnknownLanguageException) {
                    continue; // taal niet in template-set — overslaan.
                }

                $actual = $afasByItemcode[$variant->afasSamenstellingItemcode]->name ?? '';
                if ($expected === $actual) {
                    continue;
                }

                $drift[] = new NameDriftRow(
                    afasItemcode: $variant->afasSamenstellingItemcode,
                    groupName: $group->name,
                    familyHead: $group->familyHeadItemcode,
                    baseName: $base->name,
                    languageCode: $base->languageCode,
                    accessoireItemcode: $variant->accessoireItemcode,
                    accessoireLabel: $accessoire?->label,
                    expected: $expected,
                    actual: $actual,
                );
            }
        }

        return $drift;
    }
}
