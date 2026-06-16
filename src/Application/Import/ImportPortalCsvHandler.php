<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

use Defibrion\Samenstellingen\Application\Group\SyncAllGroups;
use Defibrion\Samenstellingen\Application\Group\SyncAllGroupsHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
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
use RuntimeException;

final readonly class ImportPortalCsvHandler
{
    public function __construct(
        private PortalCsvReader $reader,
        private GroupRepository $groupRepository,
        private GroupBaseRepository $baseRepository,
        private GroupBaseItemRepository $baseItemRepository,
        private GroupVariantRepository $variantRepository,
        private AfasSamenstellingLookup $lookup,
        private AccessoireRepository $accessoireRepository,
        private BomBlacklistRepository $bomBlacklistRepository,
        private SyncAllGroupsHandler $syncAllGroups,
        private AfasArticleRepository $afasArticles,
    ) {
    }

    private function articleNameFor(string $itemcode): string
    {
        $article = $this->afasArticles->findByItemcode($itemcode);

        return $article === null ? '' : $article->name;
    }

    public function __invoke(ImportPortalCsv $command): PortalImportSummary
    {
        $blockedBomCodes = $this->loadBlockedBomCodes();
        // Bases die de user handmatig heeft gepind (via group:add-base-from-afas of
        // via een eerdere portal-CSV-import) — gebruikt om ambigue article-rijen op te
        // lossen: als precies één van de kandidaten al gepind is in onze tool, kiezen
        // we die ipv ambigu te rapporteren.
        $pinnedAfasCodes = $this->baseRepository->findAllAfasItemcodes();

        $summary = new PortalImportSummary();
        $rowsByGroep = $this->groupRows($command->csvPath, $summary);
        $this->prevalidateResolvable($rowsByGroep, $blockedBomCodes, $pinnedAfasCodes, $summary);

        // Puur additief/idempotent — de import wist nooit iets:
        // - Per CSV-groep: insert als 'ie niet bestaat; bestaande blijft met haar
        //   model_name + group_accessoires. Bases zijn insert-if-not-exists.
        // - Regenereer variants per geraakte groep zodat de matrix klopt.
        // Groepen die niet (meer) in de CSV staan blijven ongemoeid; verwijderen
        // gaat altijd expliciet via `group:remove-base` / het verwijder-pad.
        foreach ($rowsByGroep as $groep => $rows) {
            $familyHead = $this->resolveFamilyHead($rows, $blockedBomCodes, $pinnedAfasCodes);
            if ($familyHead === null) {
                continue;
            }

            if ($this->groupRepository->findByFamilyHeadItemcode($familyHead) === null) {
                try {
                    $this->groupRepository->save(new Group($groep, $familyHead));
                    ++$summary->groupsCreated;
                } catch (GroupAlreadyExistsException) {
                    // Mogelijk als de groep-naam botst met een bestaande andere groep.
                }
            }

            foreach ($rows as $row) {
                $this->importRow($groep, $familyHead, $row, $blockedBomCodes, $pinnedAfasCodes, $summary);
            }

            $this->variantRepository->regenerateForGroup($familyHead);
        }

        // Na import → match alle varianten meteen tegen de huidige AFAS-snapshot.
        // Bij lege snapshot rapporteert SyncAllGroupsHandler dat in z'n summary.
        $summary->sync = ($this->syncAllGroups)(new SyncAllGroups());

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
     * @param list<string> $pinnedAfasCodes
     */
    private function prevalidateResolvable(array $rowsByGroep, array $blockedBomCodes, array $pinnedAfasCodes, PortalImportSummary $summary): void
    {
        foreach ($rowsByGroep as $groep => $rows) {
            foreach ($rows as $row) {
                $candidates = $this->sellableCandidatesFor($row['code'], $blockedBomCodes, $row['language'], $pinnedAfasCodes);

                if ($candidates === []) {
                    $summary->unresolved[] = [
                        'groep' => $groep,
                        'code' => $row['code'],
                        'articleName' => $this->articleNameFor($row['code']),
                        'reason' => 'Geen base-samenstelling gevonden (BOM moet article-code, reanimatiekit 70112 en een stickerset 81xxx bevatten, en mag geen geregistreerde accessoire bevatten).',
                    ];
                    continue;
                }

                if (count($candidates) > 1) {
                    $codes = array_map(static fn (AfasSamenstelling $s) => $s->itemcode, $candidates);
                    $summary->unresolved[] = [
                        'groep' => $groep,
                        'code' => $row['code'],
                        'articleName' => $this->articleNameFor($row['code']),
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
                    'articleName' => $this->articleNameFor($row->code),
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
    /**
     * @param list<array{code: string, item: string, language: string}> $rows
     * @param list<string> $blockedBomCodes
     * @param list<string> $pinnedAfasCodes
     */
    private function resolveFamilyHead(array $rows, array $blockedBomCodes, array $pinnedAfasCodes): ?string
    {
        foreach ($rows as $row) {
            $candidates = $this->sellableCandidatesFor($row['code'], $blockedBomCodes, $row['language'], $pinnedAfasCodes);
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
     * + stickerset (81xxx) en GEEN geregistreerde accessoire. Voor alle talen.
     *
     * @param list<string> $blockedBomCodes
     * @param list<string> $pinnedAfasCodes
     * @return list<AfasSamenstelling>
     */
    private function sellableCandidatesFor(string $articleCode, array $blockedBomCodes, string $language, array $pinnedAfasCodes): array
    {
        $bases = $this->lookup->findCanonicalBasesContaining($articleCode, $blockedBomCodes);

        // Stickerset (81xxx) is alleen verplicht voor de talen met een eigen
        // stickerset — NL/FR/DK/DE (spiegelt StickerPolicy::STICKER_FOR_LANGUAGE).
        // EN/UK + internationaal mogen zonder: de internationale stickerset 81611
        // is out of stock en uit die BOMs gehaald. Zie PLAN-AFAS.md §37.
        $firstLanguage = strtoupper(explode('/', trim($language))[0]);
        $stickerRequired = in_array($firstLanguage, ['NL', 'FR', 'DK', 'DE'], true);

        $candidates = array_values(array_filter(
            $bases,
            static function (AfasSamenstelling $s) use ($stickerRequired): bool {
                $bom = $s->bomItemcodes;
                if (!in_array('70112', $bom, true)) {
                    return false;
                }
                if (!$stickerRequired) {
                    return true;
                }
                foreach ($bom as $code) {
                    if (str_starts_with($code, '81')) {
                        return true;
                    }
                }

                return false;
            },
        ));

        // Bij ambiguïteit: als precies één kandidaat al gepind is (handmatig vastgezet
        // via group:add-base-from-afas of vorige import), respecteer die keuze.
        if (count($candidates) > 1 && $pinnedAfasCodes !== []) {
            $pinned = array_fill_keys($pinnedAfasCodes, true);
            $matching = array_values(array_filter(
                $candidates,
                static fn (AfasSamenstelling $s): bool => isset($pinned[$s->itemcode]),
            ));
            if (count($matching) === 1) {
                return $matching;
            }
        }

        return $candidates;
    }

    /**
     * @param array{code: string, item: string, language: string} $row
     * @param list<string> $blockedBomCodes
     */
    /**
     * @param array{code: string, item: string, language: string} $row
     * @param list<string> $blockedBomCodes
     * @param list<string> $pinnedAfasCodes
     */
    private function importRow(string $groep, string $familyHead, array $row, array $blockedBomCodes, array $pinnedAfasCodes, PortalImportSummary $summary): void
    {
        $candidates = $this->sellableCandidatesFor($row['code'], $blockedBomCodes, $row['language'], $pinnedAfasCodes);
        // Sla onresolveerbare (0) en ambigue (>1) rijen over — die staan al in summary->unresolved.
        if (count($candidates) !== 1) {
            return;
        }

        foreach ($candidates as $samenstelling) {
            // Slice 41: itemcode is leidend. Eerst checken of de SKU al een
            // base heeft in de groep — zo behouden we de bestaande naam,
            // die kan zijn bijgewerkt door eerdere `names:fix-drift`-runs.
            $persisted = $samenstelling->itemcode !== ''
                ? $this->baseRepository->findByAfasItemcodeInGroup($familyHead, $samenstelling->itemcode)
                : null;

            if ($persisted === null) {
                // Fallback voor SKU-loze rijen of nieuwe SKU: probeer save,
                // val terug op naam-lookup wanneer een race-condition (SKU-conflict)
                // of legacy name-UNIQUE optreedt.
                try {
                    $persisted = $this->baseRepository->saveForGroup(
                        $familyHead,
                        new GroupBase(null, $samenstelling->name, $row['language'], $samenstelling->itemcode),
                    );
                    ++$summary->basesCreated;
                } catch (BaseAlreadyExistsException) {
                    $persisted = $samenstelling->itemcode !== ''
                        ? $this->baseRepository->findByAfasItemcodeInGroup($familyHead, $samenstelling->itemcode)
                        : $this->findExistingBase($familyHead, $samenstelling->name);
                }
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
