<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Import;

use Defibrion\Samenstellingen\Domain\Import\CsvSamenstellingenReader;
use Defibrion\Samenstellingen\Domain\Import\CsvSamenstellingenRow;
use Generator;
use RuntimeException;

final readonly class FileCsvSamenstellingenReader implements CsvSamenstellingenReader
{
    public function read(string $path): iterable
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf("CSV-bestand niet leesbaar: '%s'.", $path));
        }

        return $this->generate($path);
    }

    /**
     * @return Generator<int, CsvSamenstellingenRow>
     */
    private function generate(string $path): Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf("Kon CSV niet openen: '%s'.", $path));
        }

        try {
            $header = fgetcsv($handle);
            if (!is_array($header)) {
                return;
            }

            $colIndex = [];
            foreach ($header as $i => $col) {
                if (is_string($col)) {
                    $colIndex[trim($col)] = $i;
                }
            }
            $samenstellingIdx = $colIndex['samenstelling_itemcode'] ?? null;
            $samenstellingNaamIdx = $colIndex['samenstelling_naam'] ?? null;
            $aedIdx = $colIndex['aed_article'] ?? null;
            $aedNaamIdx = $colIndex['aed_article_naam'] ?? null;

            if ($samenstellingIdx === null || $samenstellingNaamIdx === null || $aedIdx === null || $aedNaamIdx === null) {
                throw new RuntimeException(
                    'CSV header mist verwachte kolommen: samenstelling_itemcode, samenstelling_naam, aed_article, aed_article_naam.',
                );
            }

            while (($row = fgetcsv($handle)) !== false) {
                $samenstellingItemcode = self::cell($row, $samenstellingIdx);
                $samenstellingNaam = self::cell($row, $samenstellingNaamIdx);
                $aedArticle = self::cell($row, $aedIdx);
                $aedArticleNaam = self::cell($row, $aedNaamIdx);
                if ($samenstellingItemcode === '' || $aedArticle === '') {
                    continue;
                }
                yield new CsvSamenstellingenRow(
                    $samenstellingItemcode,
                    $samenstellingNaam,
                    $aedArticle,
                    $aedArticleNaam,
                );
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<int, mixed> $row
     */
    private static function cell(array $row, int $index): string
    {
        $value = $row[$index] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
