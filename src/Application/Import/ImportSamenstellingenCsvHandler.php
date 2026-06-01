<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Defibrion\Samenstellingen\Domain\Import\CsvSamenstellingenReader;
use Defibrion\Samenstellingen\Domain\Import\CsvSamenstellingenRow;

final readonly class ImportSamenstellingenCsvHandler
{
    public function __construct(
        private CsvSamenstellingenReader $reader,
        private GroupBaseRepository $baseRepository,
        private AccessoireRepository $accessoireRepository,
        private GroupAccessoireRepository $linkRepository,
        private GroupVariantRepository $variantRepository,
    ) {
    }

    public function __invoke(ImportSamenstellingenCsv $command): ImportSummary
    {
        $summary = new ImportSummary();

        $baseNamesByAedArticle = $this->collectBaseNamesByAedArticle($command->csvPath, $summary);
        $accessoireLabelsByItemcode = $this->collectAccessoireLabels($command->csvPath);

        // Slice 41: naam-UNIQUE op group_bases is verwijderd. Idempotentie
        // voor deze legacy importer (geen SKU's in de CSV) doen we hier
        // app-level via een naam-set; SKU-flow loopt via de portal-import.
        $existingNames = [];
        foreach ($this->baseRepository->findAllForGroup($command->familyHeadItemcode) as $existing) {
            $existingNames[$existing->name] = true;
        }

        foreach ($baseNamesByAedArticle as $name) {
            if (isset($existingNames[$name])) {
                ++$summary->basesSkipped;
                continue;
            }
            try {
                $this->baseRepository->saveForGroup(
                    $command->familyHeadItemcode,
                    new GroupBase(null, $name, $this->detectLanguage($name)),
                );
                ++$summary->basesCreated;
                $existingNames[$name] = true;
            } catch (BaseAlreadyExistsException) {
                ++$summary->basesSkipped;
            }
        }

        foreach ($accessoireLabelsByItemcode as $rawItemcode => $label) {
            $itemcode = (string) $rawItemcode;
            try {
                $this->accessoireRepository->save(new Accessoire($itemcode, $label));
                ++$summary->accessoiresCreated;
            } catch (AccessoireAlreadyExistsException) {
                ++$summary->accessoiresSkipped;
            }

            try {
                $this->linkRepository->link($command->familyHeadItemcode, $itemcode);
                ++$summary->accessoireLinksCreated;
            } catch (AccessoireAlreadyLinkedException) {
                ++$summary->accessoireLinksSkipped;
            }
        }

        $this->variantRepository->regenerateForGroup($command->familyHeadItemcode);

        return $summary;
    }

    /**
     * @return array<string, string> aedArticle → base name (uit base-only-rij; valt terug op samenstelling_naam van variant als geen base-only-rij gevonden)
     */
    private function collectBaseNamesByAedArticle(string $csvPath, ImportSummary $summary): array
    {
        $baseNames = [];
        $fallbackNames = [];

        foreach ($this->reader->read($csvPath) as $row) {
            ++$summary->rowsProcessed;
            if ($row->aedArticle === '') {
                continue;
            }
            if ($row->isBaseOnly()) {
                $baseNames[$row->aedArticle] = $row->samenstellingNaam !== ''
                    ? $row->samenstellingNaam
                    : $row->aedArticleNaam;
                continue;
            }
            if (!isset($fallbackNames[$row->aedArticle]) && $row->aedArticleNaam !== '') {
                $fallbackNames[$row->aedArticle] = $row->aedArticleNaam;
            }
        }

        foreach ($fallbackNames as $aed => $name) {
            $baseNames[$aed] ??= $name;
        }

        return $baseNames;
    }

    /**
     * @return array<string, string> accessoire-itemcode → label (eerste niet-lege variant-naam-suffix wint)
     */
    private function collectAccessoireLabels(string $csvPath): array
    {
        $labels = [];
        foreach ($this->reader->read($csvPath) as $row) {
            $accessoireItemcode = $row->extractAccessoireItemcode();
            if ($accessoireItemcode === null || isset($labels[$accessoireItemcode])) {
                continue;
            }
            $labels[$accessoireItemcode] = $this->parseAccessoireLabel($row, $accessoireItemcode);
        }

        return $labels;
    }

    private function parseAccessoireLabel(CsvSamenstellingenRow $row, string $itemcode): string
    {
        foreach ([' avec ', ' + ', ' with ', ' incl. '] as $delimiter) {
            $position = strripos($row->samenstellingNaam, $delimiter);
            if ($position !== false) {
                $tail = trim(substr($row->samenstellingNaam, $position + strlen($delimiter)));
                if ($tail !== '') {
                    return $tail;
                }
            }
        }

        return sprintf('Accessoire %s', $itemcode);
    }

    /**
     * Legacy slice 7-importer: detecteer taal uit AED-component-naam.
     * Voor "echte" imports gebruikt de gebruiker `group:import-portal-csv` met `Taal`-kolom.
     */
    private function detectLanguage(string $name): string
    {
        $patterns = [
            'NL' => '/\b(Nederlands|NL)\b/i',
            'FR' => '/\b(French|FR|francais|française)\b/iu',
            'DE' => '/\b(German|DE|Deutsch)\b/i',
            'EN' => '/\b(English|EN)\b/i',
            'UK' => '/\bUK\b/i',
            'ES' => '/\b(Spanish|ES|Español)\b/iu',
            'IT' => '/\b(Italian|IT)\b/i',
            'SE' => '/\b(Swedish|SE)\b/i',
            'NO' => '/\b(Norwegian|NO)\b/i',
            'DK' => '/\b(Danish|DK)\b/i',
            'FI' => '/\b(Finnish|FI)\b/i',
            'PL' => '/\b(Polish|PL)\b/i',
            'CZ' => '/\b(Czech|CZ)\b/i',
            'HU' => '/\b(Hungarian|HU)\b/i',
            'EL' => '/\b(Greek|EL|GR)\b/i',
            'HR' => '/\b(Croatian|HR|Kroatian)\b/i',
            'SK' => '/\b(Slovak|SK)\b/i',
            'SL' => '/\b(Slovenian|SL)\b/i',
            'LV' => '/\b(Latvian|LV)\b/i',
            'LT' => '/\b(Lithuanian|LT)\b/i',
            'RO' => '/\b(Romanian|RO)\b/i',
            'WAL' => '/\bWAL\b/i',
        ];
        foreach ($patterns as $code => $regex) {
            if (preg_match($regex, $name) === 1) {
                return $code;
            }
        }

        return 'NL';
    }
}
