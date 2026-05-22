<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Import;

use Defibrion\Samenstellingen\Domain\Import\PortalCsvReader;
use Defibrion\Samenstellingen\Domain\Import\PortalCsvRow;
use Generator;
use RuntimeException;

final readonly class FilePortalCsvReader implements PortalCsvReader
{
    public function read(string $path): iterable
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf("CSV-bestand niet leesbaar: '%s'.", $path));
        }

        return $this->generate($path);
    }

    /**
     * @return Generator<int, PortalCsvRow>
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
            $codeIdx = $colIndex['Code'] ?? null;
            $groepIdx = $colIndex['Groep'] ?? null;
            $itemIdx = $colIndex['Item'] ?? null;
            $merkIdx = $colIndex['Merknaam'] ?? null;

            if ($codeIdx === null || $groepIdx === null || $itemIdx === null) {
                throw new RuntimeException(
                    'Portal-CSV header mist verwachte kolommen: Code, Groep, Item.',
                );
            }

            while (($row = fgetcsv($handle)) !== false) {
                $code = self::cell($row, $codeIdx);
                $groep = self::cell($row, $groepIdx);
                $item = self::cell($row, $itemIdx);
                $merknaam = $merkIdx !== null ? self::cell($row, $merkIdx) : '';
                if ($code === '') {
                    continue;
                }
                yield new PortalCsvRow($code, $groep, $item, $merknaam);
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
