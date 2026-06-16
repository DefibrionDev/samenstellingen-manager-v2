<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Import;

use Defibrion\Samenstellingen\Domain\Import\PortalCsvRow;
use Defibrion\Samenstellingen\Infrastructure\Import\FilePortalCsvReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilePortalCsvReaderTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
    }

    private function writeCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'portalcsv');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    #[Test]
    public function readsOptionalConnectiviteitColumn(): void
    {
        $path = $this->writeCsv(
            "Code,Groep,Item,Merknaam,Taal,Connectiviteit\n"
            . "21018,Mindray C1 semi,Mindray C1 semi 4G,Mindray,NL,4G\n"
        );

        /** @var list<PortalCsvRow> $rows */
        $rows = iterator_to_array((new FilePortalCsvReader())->read($path));

        self::assertCount(1, $rows);
        self::assertSame('21018', $rows[0]->code);
        self::assertSame('NL', $rows[0]->taal);
        self::assertSame('4G', $rows[0]->connectivityLabel());
    }

    #[Test]
    public function connectivityIsNullWhenColumnAbsent(): void
    {
        $path = $this->writeCsv(
            "Code,Groep,Item,Merknaam,Taal\n"
            . "21012,Mindray C1 vol,Mindray C1 vol,Mindray,NL\n"
        );

        $rows = iterator_to_array((new FilePortalCsvReader())->read($path));

        self::assertCount(1, $rows);
        self::assertNull($rows[0]->connectivityLabel());
    }

    #[Test]
    public function acceptsConnectAlias(): void
    {
        $path = $this->writeCsv(
            "Code,Groep,Item,Merknaam,Taal,Connect\n"
            . "52190,Reanibex semi,Reanibex semi WiFi,Bexen,NL,WiFi\n"
        );

        $rows = iterator_to_array((new FilePortalCsvReader())->read($path));

        self::assertSame('WiFi', $rows[0]->connectivityLabel());
    }
}
