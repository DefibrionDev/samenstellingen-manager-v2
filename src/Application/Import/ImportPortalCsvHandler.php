<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingLookup;
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
    ) {
    }

    public function __invoke(ImportPortalCsv $command): PortalImportSummary
    {
        $accessoireItemcodes = $this->loadAccessoireItemcodes();

        $summary = new PortalImportSummary();
        $rowsByGroep = $this->groupRows($command->csvPath, $summary);
        $this->prevalidateResolvable($rowsByGroep, $accessoireItemcodes, $summary);

        // Bij ook maar één onresolveerbare rij: STOP. Geen wipe, geen import.
        if ($summary->unresolved !== []) {
            return $summary;
        }

        $this->wiper->wipe();

        foreach ($rowsByGroep as $groep => $rows) {
            $familyHead = $this->resolveFamilyHead($rows, $accessoireItemcodes);
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
                $this->importRow($groep, $familyHead, $row, $accessoireItemcodes, $summary);
            }

            $this->variantRepository->regenerateForGroup($familyHead);
        }

        return $summary;
    }

    /**
     * @return list<string>
     */
    private function loadAccessoireItemcodes(): array
    {
        $accessoires = $this->accessoireRepository->findAll();
        if ($accessoires === []) {
            throw new RuntimeException(
                'De accessoires-catalogus is leeg. Definieer eerst de accessoires '
                . "via `bin/samenstellingen accessoire:create <itemcode> '<label>'` voordat de portal-CSV geïmporteerd kan worden — "
                . 'zonder catalogus kan de tool base-samenstellingen niet onderscheiden van varianten.'
            );
        }

        return array_map(static fn (Accessoire $a) => $a->itemcode, $accessoires);
    }

    /**
     * @param array<string, list<array{code: string, item: string, language: string}>> $rowsByGroep
     * @param list<string> $accessoireItemcodes
     */
    private function prevalidateResolvable(array $rowsByGroep, array $accessoireItemcodes, PortalImportSummary $summary): void
    {
        foreach ($rowsByGroep as $groep => $rows) {
            foreach ($rows as $row) {
                $candidates = $this->sellableCandidatesFor($row['code'], $accessoireItemcodes);

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
     * @param list<string> $accessoireItemcodes
     */
    private function resolveFamilyHead(array $rows, array $accessoireItemcodes): ?string
    {
        foreach ($rows as $row) {
            $candidates = $this->sellableCandidatesFor($row['code'], $accessoireItemcodes);
            foreach ($candidates as $candidate) {
                $parent = $candidate->itemcodeParent;
                if ($parent !== null) {
                    return $parent;
                }

                return $candidate->itemcode;
            }
        }

        return null;
    }

    /**
     * "Verkoopbare" base-samenstellingen: BOM bevat article-code + reanimatiekit (70112)
     * + stickerset (81xxx) en GEEN geregistreerde accessoire.
     *
     * @param list<string> $accessoireItemcodes
     * @return list<AfasSamenstelling>
     */
    private function sellableCandidatesFor(string $articleCode, array $accessoireItemcodes): array
    {
        $bases = $this->lookup->findCanonicalBasesContaining($articleCode, $accessoireItemcodes);

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
     * @param list<string> $accessoireItemcodes
     */
    private function importRow(string $groep, string $familyHead, array $row, array $accessoireItemcodes, PortalImportSummary $summary): void
    {
        $candidates = $this->sellableCandidatesFor($row['code'], $accessoireItemcodes);
        if ($candidates === []) {
            // Pre-validate had dit moeten vangen; defensief overslaan.
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
