<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Import;

use Defibrion\Samenstellingen\Application\Import\ImportSamenstellingenCsv;
use Defibrion\Samenstellingen\Application\Import\ImportSamenstellingenCsvHandler;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Import\CsvSamenstellingenReader;
use Defibrion\Samenstellingen\Domain\Import\CsvSamenstellingenRow;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportSamenstellingenCsvHandlerTest extends TestCase
{
    #[Test]
    public function importsBasesAndAccessoiresFromRows(): void
    {
        $reader = new class () implements CsvSamenstellingenReader {
            public function read(string $path): iterable
            {
                yield new CsvSamenstellingenRow('52112', 'AED pakket NL', '50013', 'AED Nederlands');
                yield new CsvSamenstellingenRow('52112-60110', 'AED pakket NL + Rugzak', '50013', 'AED Nederlands');
                yield new CsvSamenstellingenRow('52112-60112', 'AED pakket NL + ARKY witte binnenkast', '50013', 'AED Nederlands');
                yield new CsvSamenstellingenRow('52124', 'Pack DAE FR', '50001', 'AED French');
                yield new CsvSamenstellingenRow('52124-60110', 'Pack DAE FR avec Sac à dos', '50001', 'AED French');
            }
        };

        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        $handler = new ImportSamenstellingenCsvHandler($reader, $bases, $accessoires, $links, $variants);
        $summary = $handler(new ImportSamenstellingenCsv('/irrelevant', '52112'));

        self::assertSame(2, $summary->basesCreated, '50013 NL + 50001 FR = 2 bases');
        self::assertSame(0, $summary->basesSkipped);
        self::assertSame(2, $summary->accessoiresCreated, '60110 + 60112 = 2 accessoires');
        self::assertSame(2, $summary->accessoireLinksCreated);
        self::assertCount(2, $bases->findAllForGroup('52112'));
        self::assertCount(2, $links->findAllForGroup('52112'));
        // 2 bases × (geen + 2 accessoires) = 6 varianten.
        self::assertCount(6, $variants->findAllForGroup('52112'));
    }

    #[Test]
    public function isIdempotent(): void
    {
        $reader = new class () implements CsvSamenstellingenReader {
            public function read(string $path): iterable
            {
                yield new CsvSamenstellingenRow('52112', 'AED pakket NL', '50013', 'AED Nederlands');
                yield new CsvSamenstellingenRow('52112-60110', 'AED pakket NL + Rugzak', '50013', 'AED Nederlands');
            }
        };

        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        $handler = new ImportSamenstellingenCsvHandler($reader, $bases, $accessoires, $links, $variants);
        $handler(new ImportSamenstellingenCsv('/irrelevant', '52112'));
        $secondRun = $handler(new ImportSamenstellingenCsv('/irrelevant', '52112'));

        self::assertSame(0, $secondRun->basesCreated);
        self::assertSame(1, $secondRun->basesSkipped);
        self::assertSame(0, $secondRun->accessoiresCreated);
        self::assertSame(1, $secondRun->accessoiresSkipped);
        self::assertSame(0, $secondRun->accessoireLinksCreated);
        self::assertSame(1, $secondRun->accessoireLinksSkipped);
    }

    #[Test]
    public function parsesAccessoireLabelFromVariantName(): void
    {
        $reader = new class () implements CsvSamenstellingenReader {
            public function read(string $path): iterable
            {
                yield new CsvSamenstellingenRow('52112', 'AED pakket NL', '50013', 'AED NL');
                yield new CsvSamenstellingenRow(
                    '52112-60112',
                    'AED pakket NL + ARKY witte binnenkast',
                    '50013',
                    'AED NL',
                );
            }
        };

        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        $handler = new ImportSamenstellingenCsvHandler($reader, $bases, $accessoires, $links, $variants);
        $handler(new ImportSamenstellingenCsv('/irrelevant', '52112'));

        $accessoire = $accessoires->findByItemcode('60112');
        self::assertNotNull($accessoire);
        self::assertSame('ARKY witte binnenkast', $accessoire->label);
    }
}
