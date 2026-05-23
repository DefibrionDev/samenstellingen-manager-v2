<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

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
    ) {
    }

    public function __invoke(ImportPortalCsv $command): PortalImportSummary
    {
        $summary = new PortalImportSummary();

        // Stap 1: groepeer CSV-rijen per Groep en valideer.
        $rowsByGroep = $this->groupRows($command->csvPath, $summary);
        $this->prevalidateResolvable($rowsByGroep, $summary);

        // Bij ook maar één onresolveerbare rij: STOP. Geen wipe, geen import.
        if ($summary->unresolved !== []) {
            return $summary;
        }

        // Stap 2: wis tool-data en importeer.
        $this->wiper->wipe();

        foreach ($rowsByGroep as $groep => $rows) {
            $familyHead = $this->resolveFamilyHead($rows);
            if ($familyHead === null) {
                // Onverwacht na prevalidatie; defensief overslaan.
                continue;
            }

            try {
                $this->groupRepository->save(new Group($groep, $familyHead));
                ++$summary->groupsCreated;
            } catch (GroupAlreadyExistsException) {
                // Wipe + nieuwe import zou geen duplicates moeten geven; defensief overslaan.
            }

            foreach ($rows as $row) {
                $this->importRow($groep, $familyHead, $row, $summary);
            }

            $this->variantRepository->regenerateForGroup($familyHead);
        }

        return $summary;
    }

    /**
     * Controleer vooraf dat elke gegroepeerde CSV-rij minstens één verkoopbare
     * AFAS-samenstelling oplevert. Voegt niet-resolveerbare rijen toe aan
     * `$summary->unresolved` zodat de aanroeper kan beslissen wel/niet te importeren.
     *
     * @param array<string, list<array{code: string, item: string, language: string}>> $rowsByGroep
     */
    private function prevalidateResolvable(array $rowsByGroep, PortalImportSummary $summary): void
    {
        foreach ($rowsByGroep as $groep => $rows) {
            foreach ($rows as $row) {
                if ($this->sellableCandidatesFor($row['code']) === []) {
                    $summary->unresolved[] = [
                        'groep' => $groep,
                        'code' => $row['code'],
                        'reason' => 'Geen verkoopbare AFAS-samenstelling gevonden (BOM moet zowel reanimatiekit 70112 als een stickerset 81xxx bevatten).',
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
     * Family-head van een groep: AFAS Itemcode_Parent van de eerste resolveerbare
     * samenstelling. Bij geen resolveerbare rijen: null (groep wordt overgeslagen).
     *
     * @param list<array{code: string, item: string, language: string}> $rows
     */
    private function resolveFamilyHead(array $rows): ?string
    {
        foreach ($rows as $row) {
            $candidates = $this->sellableCandidatesFor($row['code']);
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
     * "Verkoopbare" samenstellingen: BOM moet zowel reanimatiekit (70112) bevatten
     * als minstens één stickerset (itemcode begint met `81`). Daarmee filtert
     * de import "kale" base-only samenstellingen die Defibrion niet verkoopt eruit.
     *
     * @return list<AfasSamenstelling>
     */
    private function sellableCandidatesFor(string $articleCode): array
    {
        $candidates = $this->lookup->findCanonicalBaseOnlyContaining($articleCode);

        return array_values(array_filter(
            $candidates,
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
     */
    private function importRow(string $groep, string $familyHead, array $row, PortalImportSummary $summary): void
    {
        $candidates = $this->sellableCandidatesFor($row['code']);
        if ($candidates === []) {
            $summary->unresolved[] = [
                'groep' => $groep,
                'code' => $row['code'],
                'reason' => 'Geen verkoopbare AFAS-samenstelling gevonden (BOM moet zowel reanimatiekit 70112 als een stickerset 81xxx bevatten).',
            ];

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
