<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariants;
use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Defibrion\Samenstellingen\Application\Audit\MissingVariantRow;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Throwable;

/**
 * Bouwt plannen voor missende variant-samenstellingen en stuurt ze (via writer)
 * naar AFAS. Default dry-run; --apply schrijft. Skipt rijen die in AFAS al
 * bestaan (de huidige variant-matcher is onbetrouwbaar — wij filteren hier
 * tegen de feitelijke `afas_samenstellingen`-snapshot) en rijen waarvoor we
 * geen referentie-variant in dezelfde groep kunnen vinden om `Grp` / `CsGc`
 * van te spiegelen. Zie PLAN.md §20.
 */
final readonly class FixMissingVariantsHandler
{
    public function __construct(
        private ListMissingVariantsHandler $audit,
        private GroupRepository $groups,
        private GroupBaseRepository $bases,
        private GroupVariantRepository $variants,
        private AccessoireRepository $accessoires,
        private AfasSamenstellingenRepository $afasSamenstellingen,
        private VariantNamingPolicy $namingPolicy,
        private VariantFixMissingWriter $writer,
    ) {
    }

    public function __invoke(FixMissingVariants $command): FixMissingVariantsResult
    {
        /** @var list<MissingVariantRow> $rows */
        $rows = ($this->audit)(new ListMissingVariants());

        $existingAfasCodes = [];
        foreach ($this->afasSamenstellingen->findAll() as $samenstelling) {
            $existingAfasCodes[$samenstelling->itemcode] = true;
        }

        $referenceByFamilyHead = [];

        $plans = [];
        $skipped = [];

        foreach ($rows as $row) {
            if ($command->familyHeadItemcode !== null && $row->familyHead !== $command->familyHeadItemcode) {
                continue;
            }
            if ($row->verwachteSkuVoorstel === '' || $row->baseAfasSku === '' || $row->accessoireItemcode === '') {
                continue;
            }
            if (isset($existingAfasCodes[$row->verwachteSkuVoorstel])) {
                $skipped[] = ['itemcode' => $row->verwachteSkuVoorstel, 'reason' => 'bestaat al in AFAS'];
                continue;
            }

            $group = $this->groups->findByFamilyHeadItemcode($row->familyHead);
            if ($group === null) {
                $skipped[] = ['itemcode' => $row->verwachteSkuVoorstel, 'reason' => "groep '$row->familyHead' niet gevonden"];
                continue;
            }

            $base = $this->findBaseByAfasItemcode($row->familyHead, $row->baseAfasSku);
            if ($base === null) {
                $skipped[] = ['itemcode' => $row->verwachteSkuVoorstel, 'reason' => "base '$row->baseAfasSku' niet gevonden in groep"];
                continue;
            }

            $accessoire = $this->accessoires->findByItemcode($row->accessoireItemcode);
            if ($accessoire === null) {
                $skipped[] = ['itemcode' => $row->verwachteSkuVoorstel, 'reason' => "accessoire '$row->accessoireItemcode' niet gevonden"];
                continue;
            }

            try {
                $canonicalName = $this->namingPolicy->expectedName($group, $base, $accessoire);
            } catch (Throwable $e) {
                $skipped[] = ['itemcode' => $row->verwachteSkuVoorstel, 'reason' => 'canonical-naam: ' . $e->getMessage()];
                continue;
            }

            $referenceCode = $referenceByFamilyHead[$row->familyHead]
                ?? ($referenceByFamilyHead[$row->familyHead] = $this->findReferenceVariant($row->familyHead));
            if ($referenceCode === null) {
                $skipped[] = ['itemcode' => $row->verwachteSkuVoorstel, 'reason' => "geen referentie-variant in groep $row->familyHead"];
                continue;
            }

            $plans[] = new VariantFixMissingPlan(
                afasItemcode: $row->verwachteSkuVoorstel,
                canonicalName: $canonicalName,
                bomItemcodes: $row->verwachteBom,
                familyHeadItemcode: $row->familyHead,
                baseAfasItemcode: $row->baseAfasSku,
                referenceVariantItemcode: $referenceCode,
            );

            if ($command->limit !== null && count($plans) >= $command->limit) {
                break;
            }
        }

        $applied = 0;
        $failures = [];
        if ($command->apply) {
            foreach ($plans as $plan) {
                try {
                    $this->writer->apply($plan);
                    $applied++;
                } catch (VariantFixMissingFailedException $e) {
                    $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
                }
            }
        }

        return new FixMissingVariantsResult($plans, $applied, $failures, $skipped);
    }

    private function findBaseByAfasItemcode(string $familyHead, string $baseAfasItemcode): ?\Defibrion\Samenstellingen\Domain\Group\GroupBase
    {
        foreach ($this->bases->findAllForGroup($familyHead) as $base) {
            if ($base->afasItemcode === $baseAfasItemcode) {
                return $base;
            }
        }

        return null;
    }

    /**
     * Vind een matched variant (of base) in dezelfde groep waarvan we Grp/CsGc kunnen spiegelen.
     */
    private function findReferenceVariant(string $familyHead): ?string
    {
        foreach ($this->variants->findAllForGroup($familyHead) as $v) {
            if ($v->afasStatus === 'matched' && $v->afasSamenstellingItemcode !== null && $v->afasSamenstellingItemcode !== '') {
                return $v->afasSamenstellingItemcode;
            }
        }

        return null;
    }
}
