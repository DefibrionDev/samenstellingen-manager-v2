<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;

/**
 * Lijst ÁLLE `no_match`-varianten — variants waarvan de matcher geen AFAS-compositie
 * met de verwachte BOM kon vinden. Anders dan {@see ListMissingVariantsHandler} filtert
 * deze audit niets weg op basis van de voorgestelde itemcode: hij toont per rij óf er
 * toch al een AFAS-compositie met de verwachte itemcode bestaat (`bestaandeAfasItemcode`)
 * en óf er een compositie met exact deze BOM bestaat (`exacteBomMatchItemcode`).
 * Zo wordt zichtbaar welke no_match-varianten eigenlijk al een (afwijkende) compositie
 * in AFAS hebben. Read-only; raakt de missing-variants-audit en fix-missing niet.
 * Zie PLAN.md §11.
 */
final readonly class ListNoMatchVariantsHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private GroupVariantRepository $variantRepository,
        private GroupBaseItemRepository $baseItemRepository,
        private AfasSamenstellingenRepository $afasSamenstellingen,
    ) {
    }

    /**
     * @return list<NoMatchVariantRow>
     */
    public function __invoke(ListNoMatchVariants $command): array
    {
        $itemcodeByBomKey = $this->indexByBomKey();

        $rows = [];
        foreach ($this->groupRepository->findAll() as $group) {
            $variants = $this->variantRepository->findAllForGroup($group->familyHeadItemcode);

            $baseAfasSkuByBaseId = [];
            foreach ($variants as $variant) {
                if (
                    $variant->accessoireItemcode === null
                    && $variant->afasStatus === 'matched'
                    && $variant->afasSamenstellingItemcode !== null
                ) {
                    $baseAfasSkuByBaseId[$variant->baseId] = $variant->afasSamenstellingItemcode;
                }
            }

            foreach ($variants as $variant) {
                if ($variant->afasStatus !== 'no_match') {
                    continue;
                }
                $row = $this->buildRow($group->name, $group->familyHeadItemcode, $variant, $baseAfasSkuByBaseId, $itemcodeByBomKey);
                if ($row->verwachteBom === []) {
                    // Geen afleidbare BOM (base zonder items) → niets zinvols te tonen.
                    continue;
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * BOM-key → itemcode. Canonieke composities winnen van duplicaten met dezelfde BOM.
     *
     * @return array<string, string>
     */
    private function indexByBomKey(): array
    {
        $byBomKey = [];
        foreach ($this->afasSamenstellingen->findAll() as $samenstelling) {
            $key = $samenstelling->bomKey();
            if (!isset($byBomKey[$key]) || $samenstelling->isCanonical()) {
                $byBomKey[$key] = $samenstelling->itemcode;
            }
        }

        return $byBomKey;
    }

    /**
     * @param array<int, string>    $baseAfasSkuByBaseId
     * @param array<string, string> $itemcodeByBomKey
     */
    private function buildRow(
        string $groupName,
        string $familyHead,
        GroupVariant $variant,
        array $baseAfasSkuByBaseId,
        array $itemcodeByBomKey,
    ): NoMatchVariantRow {
        $baseAfasSku = $baseAfasSkuByBaseId[$variant->baseId] ?? '';

        $bom = [];
        foreach ($this->baseItemRepository->findAllForBase($variant->baseId) as $item) {
            $bom[] = $item->itemcode;
        }
        if ($variant->accessoireItemcode !== null) {
            $bom[] = $variant->accessoireItemcode;
        }

        $accessoireItemcode = $variant->accessoireItemcode ?? '';
        $verwachteItemcode = '';
        if ($baseAfasSku !== '' && $accessoireItemcode !== '') {
            $verwachteItemcode = $baseAfasSku . '-' . $accessoireItemcode;
        } elseif ($baseAfasSku !== '') {
            $verwachteItemcode = $baseAfasSku;
        }

        $expectedBom = $this->normaliseBom($bom);

        $bestaandeAfasItemcode = null;
        $ontbrekendeItemcodes = [];
        $extraItemcodes = [];
        if ($verwachteItemcode !== '') {
            $bestaande = $this->afasSamenstellingen->findByItemcode($verwachteItemcode);
            if ($bestaande !== null) {
                $bestaandeAfasItemcode = $bestaande->itemcode;
                // $bestaande->bomItemcodes is al genormaliseerd (trim/dedup/sort).
                $ontbrekendeItemcodes = array_values(array_diff($expectedBom, $bestaande->bomItemcodes));
                $extraItemcodes = array_values(array_diff($bestaande->bomItemcodes, $expectedBom));
            }
        }

        $exacteBomMatchItemcode = $itemcodeByBomKey[implode(',', $expectedBom)] ?? null;

        $actie = match (true) {
            $verwachteItemcode === '' => NoMatchVariantRow::ACTIE_BASE_NIET_GEMATCHT,
            $bestaandeAfasItemcode !== null => NoMatchVariantRow::ACTIE_BESTAAT_AL,
            $exacteBomMatchItemcode !== null => NoMatchVariantRow::ACTIE_BOM_ELDERS,
            default => NoMatchVariantRow::ACTIE_AANMAAKBAAR,
        };

        return new NoMatchVariantRow(
            $groupName,
            $familyHead,
            $variant->baseName,
            $baseAfasSku,
            $accessoireItemcode,
            $variant->accessoireLabel ?? '',
            $expectedBom,
            $verwachteItemcode,
            $bestaandeAfasItemcode,
            $exacteBomMatchItemcode,
            $ontbrekendeItemcodes,
            $extraItemcodes,
            $actie,
        );
    }

    /**
     * Normaliseert een BOM op dezelfde manier als {@see AfasSamenstelling} (trim, dedup,
     * sort). De comma-join hiervan is exact de sleutel die {@see AfasSamenstelling::bomKey()}
     * produceert, dus geschikt voor lookups in de BOM-key-index én voor een set-diff.
     *
     * @param list<string> $codes
     *
     * @return list<string>
     */
    private function normaliseBom(array $codes): array
    {
        $seen = [];
        $normalised = [];
        foreach ($codes as $code) {
            $trimmed = trim($code);
            if ($trimmed === '' || isset($seen[$trimmed])) {
                continue;
            }
            $normalised[] = $trimmed;
            $seen[$trimmed] = true;
        }
        sort($normalised);

        return $normalised;
    }
}
