<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingLookup;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\BaseItemAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Defibrion\Samenstellingen\Domain\Import\PortalCsvReader;
use Defibrion\Samenstellingen\Domain\Tool\ToolDataWiper;
use RuntimeException;

final readonly class ImportPortalCsvHandler
{
    public function __construct(
        private PortalCsvReader $reader,
        private ToolDataWiper $wiper,
        private GroupRepository $groupRepository,
        private GroupBaseRepository $baseRepository,
        private GroupBaseItemRepository $baseItemRepository,
        private GroupVariantRepository $variantRepository,
        private AfasSamenstellingLookup $lookup,
        private AccessoireRepository $accessoireRepository,
        private BomBlacklistRepository $bomBlacklistRepository,
    ) {
    }

    public function __invoke(ImportPortalCsv $command): PortalImportSummary
    {
        $blockedBomCodes = $this->loadBlockedBomCodes();

        $summary = new PortalImportSummary();
        $rowsByGroep = $this->groupRows($command->csvPath, $summary);
        $this->prevalidateResolvable($rowsByGroep, $blockedBomCodes, $summary);

        // Non-blocking: unresolved rijen blijven in het rapport, maar de resolveerbare
        // rijen worden gewoon geïmporteerd. importRow/resolveFamilyHead negeren rijen
        // met 0 of >1 kandidaten.
        $this->wiper->wipe();

        foreach ($rowsByGroep as $groep => $rows) {
            $familyHead = $this->resolveFamilyHead($rows, $blockedBomCodes);
            if ($familyHead === null) {
                continue;
            }

            try {
                $this->groupRepository->save(new Group($groep, $familyHead));
                ++$summary->groupsCreated;
            } catch (GroupAlreadyExistsException) {
                // Defensief: na wipe niet te verwachten.
            }

            foreach ($rows as $row) {
                $this->importRow($groep, $familyHead, $row, $blockedBomCodes, $summary);
            }

            $this->variantRepository->regenerateForGroup($familyHead);
        }

        return $summary;
    }

    /**
     * Codes die — als ze in een AFAS-samenstelling's BOM staan — die samenstelling
     * diskwalificeren als base-kandidaat. Combineert de geregistreerde accessoires
     * (variant-markers in de matrix) met de aparte BOM-blacklist (codes die om
     * andere redenen geen base mogen aanduiden, bv. Waalse stickerset).
     *
     * @return list<string>
     */
    private function loadBlockedBomCodes(): array
    {
        $accessoires = $this->accessoireRepository->findAll();
        if ($accessoires === []) {
            throw new RuntimeException(
                'De accessoires-catalogus is leeg. Definieer eerst de accessoires '
                . "via `bin/samenstellingen accessoire:create <itemcode> '<label>'` voordat de portal-CSV geïmporteerd kan worden — "
                . 'zonder catalogus kan de tool base-samenstellingen niet onderscheiden van varianten.'
            );
        }

        $codes = array_map(static fn (Accessoire $a) => $a->itemcode, $accessoires);
        foreach ($this->bomBlacklistRepository->findAll() as $entry) {
            $codes[] = $entry->itemcode;
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param array<string, list<array{code: string, item: string, language: string}>> $rowsByGroep
     * @param list<string> $blockedBomCodes
     */
    private function prevalidateResolvable(array $rowsByGroep, array $blockedBomCodes, PortalImportSummary $summary): void
    {
        foreach ($rowsByGroep as $groep => $rows) {
            foreach ($rows as $row) {
                $candidates = $this->sellableCandidatesFor($row['code'], $blockedBomCodes);

                if ($candidates === []) {
                    $summary->unresolved[] = [
                        'groep' => $groep,
                        'code' => $row['code'],
                        'reason' => 'Geen base-samenstelling gevonden (BOM moet article-code, reanimatiekit 70112 en een stickerset 81xxx bevatten, en mag geen geregistreerde accessoire bevatten).',
                    ];
                    continue;
                }

                if (count($candidates) > 1) {
                    $codes = array_map(static fn (AfasSamenstelling $s) => $s->itemcode, $candidates);
                    $summary->unresolved[] = [
                        'groep' => $groep,
                        'code' => $row['code'],
                        'reason' => sprintf(
                            'Ambigu: AFAS bevat %d base-kandidaten (%s) — los op in AFAS voordat de import opnieuw draait.',
                            count($candidates),
                            implode(', ', $codes),
                        ),
                    ];
                }
            }
        }
    }

    /**
     * @return array<string, list<array{code: string, item: string, language: string}>>
     */
    private function groupRows(string $csvPath, PortalImportSummary $summary): array
    {
        $rowsByGroep = [];
        foreach ($this->reader->read($csvPath) as $row) {
            ++$summary->rowsProcessed;
            if (!$row->hasGroep()) {
                ++$summary->rowsSkippedNoGroep;
                continue;
            }
            $language = $row->languageCode();
            if ($language === null) {
                $summary->unresolved[] = [
                    'groep' => $row->groep,
                    'code' => $row->code,
                    'reason' => "Geen taal opgegeven in CSV-kolom 'Taal' — taal is verplicht voor een base.",
                ];
                continue;
            }
            $rowsByGroep[$row->groep][] = [
                'code' => $row->code,
                'item' => $row->item,
                'language' => $language,
            ];
        }

        return $rowsByGroep;
    }

    /**
     * @param list<array{code: string, item: string, language: string}> $rows
     * @param list<string> $blockedBomCodes
     */
    private function resolveFamilyHead(array $rows, array $blockedBomCodes): ?string
    {
        foreach ($rows as $row) {
            $candidates = $this->sellableCandidatesFor($row['code'], $blockedBomCodes);
            if (count($candidates) !== 1) {
                // Onresolveerbare (0) en ambigue (>1) rijen kunnen geen family-head bepalen.
                continue;
            }
            $candidate = $candidates[0];
            $parent = $candidate->itemcodeParent;
            if ($parent !== null) {
                return $parent;
            }

            return $candidate->itemcode;
        }

        return null;
    }

    /**
     * "Verkoopbare" base-samenstellingen: BOM bevat article-code + reanimatiekit (70112)
     * + stickerset (81xxx) en GEEN geregistreerde accessoire.
     *
     * @param list<string> $blockedBomCodes
     * @return list<AfasSamenstelling>
     */
    private function sellableCandidatesFor(string $articleCode, array $blockedBomCodes): array
    {
        $bases = $this->lookup->findCanonicalBasesContaining($articleCode, $blockedBomCodes);

        return array_values(array_filter(
            $bases,
            static function (AfasSamenstelling $s): bool {
                $bom = $s->bomItemcodes;
                if (!in_array('70112', $bom, true)) {
                    return false;
                }
                foreach ($bom as $code) {
                    if (str_starts_with($code, '81')) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    /**
     * @param array{code: string, item: string, language: string} $row
     * @param list<string> $blockedBomCodes
     */
    private function importRow(string $groep, string $familyHead, array $row, array $blockedBomCodes, PortalImportSummary $summary): void
    {
        $candidates = $this->sellableCandidatesFor($row['code'], $blockedBomCodes);
        // Sla onresolveerbare (0) en ambigue (>1) rijen over — die staan al in summary->unresolved.
        if (count($candidates) !== 1) {
            return;
        }

        foreach ($candidates as $samenstelling) {
            $persisted = null;
            try {
                $persisted = $this->baseRepository->saveForGroup(
                    $familyHead,
                    new GroupBase(null, $samenstelling->name, $row['language']),
                );
                ++$summary->basesCreated;
            } catch (BaseAlreadyExistsException) {
                $persisted = $this->findExistingBase($familyHead, $samenstelling->name);
            }

            if ($persisted?->id === null) {
                continue;
            }

            foreach ($samenstelling->bomItemcodes as $itemcode) {
                try {
                    $this->baseItemRepository->saveForBase(
                        $persisted->id,
                        new GroupBaseItem($itemcode, $itemcode),
                    );
                    ++$summary->baseItemsCreated;
                } catch (BaseItemAlreadyExistsException) {
                    // Reeds aanwezig, overslaan.
                }
            }
        }
    }

    private function findExistingBase(string $familyHead, string $name): ?GroupBase
    {
        foreach ($this->baseRepository->findAllForGroup($familyHead) as $base) {
            if ($base->name === $name) {
                return $base;
            }
        }

        return null;
    }
}
