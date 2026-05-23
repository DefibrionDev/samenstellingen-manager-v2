<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Import;

use Defibrion\Samenstellingen\Application\Import\ImportPortalCsv;
use Defibrion\Samenstellingen\Application\Import\ImportPortalCsvHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingLookup;
use Defibrion\Samenstellingen\Domain\Import\PortalCsvReader;
use Defibrion\Samenstellingen\Domain\Import\PortalCsvRow;
use Defibrion\Samenstellingen\Domain\Tool\ToolDataWiper;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ImportPortalCsvHandlerTest extends TestCase
{
    #[Test]
    public function abortsWhenAccessoireCatalogueIsEmpty(): void
    {
        $bag = $this->emptyBag();
        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex 100 Semi-Auto', 'AED NL', '', 'NL'),
        ]);

        $handler = new ImportPortalCsvHandler(
            $reader,
            $bag['wiper'],
            $bag['groups'],
            $bag['bases'],
            $bag['baseItems'],
            $bag['variants'],
            $bag['lookup'],
            $bag['accessoires'],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/accessoire:create/i');

        $handler(new ImportPortalCsv('/irrelevant.csv'));
    }

    #[Test]
    public function reportsUnresolvedWhenNoBaseCandidateExists(): void
    {
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'ARKY oranje buitenkast'));
        // Snapshot bevat alleen een variant (met accessoire), geen base.
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142-60110', 'Variant', null, ['50013', '60110', '70112', '81111']),
        ]);

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex 100 Semi-Auto', 'AED NL', '', 'NL'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertCount(1, $summary->unresolved);
        self::assertSame('50013', $summary->unresolved[0]['code']);
        self::assertStringContainsString('Geen base', $summary->unresolved[0]['reason']);
        self::assertSame(0, $summary->basesCreated);
    }

    #[Test]
    public function reportsAmbiguityWhenMultipleBaseCandidatesExist(): void
    {
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'ARKY oranje buitenkast'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('11145', 'AED pakket NL extra', null, ['50013', '70112', '81111', '90099']),
        ]);

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex 100 Semi-Auto', 'AED NL', '', 'NL'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertCount(1, $summary->unresolved);
        self::assertSame('50013', $summary->unresolved[0]['code']);
        self::assertStringContainsString('Ambigu', $summary->unresolved[0]['reason']);
        self::assertStringContainsString('11142', $summary->unresolved[0]['reason']);
        self::assertStringContainsString('11145', $summary->unresolved[0]['reason']);
        self::assertSame(0, $summary->basesCreated);
    }

    #[Test]
    public function importsWhenExactlyOneBaseCandidateExists(): void
    {
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'ARKY oranje buitenkast'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('11142-60110', 'Variant met buitenkast', '11142', ['50013', '60110', '70112', '81111']),
        ]);

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex 100 Semi-Auto', 'AED NL', '', 'NL'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertSame([], $summary->unresolved);
        self::assertSame(1, $summary->groupsCreated);
        self::assertSame(1, $summary->basesCreated);
    }

    #[Test]
    public function languageSuffixedBaseResolvesUnambiguously(): void
    {
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'ARKY oranje buitenkast'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142-FR', 'Pack DAE FR', null, ['50013', '70112', '81211']),
            new AfasSamenstelling('11142-FR-60110', 'Variant FR', '11142-FR', ['50013', '60110', '70112', '81211']),
        ]);

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex 100 Semi-Auto FR', 'AED FR', '', 'FR'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertSame([], $summary->unresolved);
        self::assertSame(1, $summary->basesCreated);
    }

    /**
     * @param array<string, mixed> $bag
     */
    private function makeHandler(array $bag, PortalCsvReader $reader): ImportPortalCsvHandler
    {
        return new ImportPortalCsvHandler(
            $reader,
            $bag['wiper'],
            $bag['groups'],
            $bag['bases'],
            $bag['baseItems'],
            $bag['variants'],
            $bag['lookup'],
            $bag['accessoires'],
        );
    }

    /**
     * @return array{
     *     wiper: ToolDataWiper,
     *     groups: InMemoryGroupRepository,
     *     bases: InMemoryGroupBaseRepository,
     *     baseItems: InMemoryGroupBaseItemRepository,
     *     variants: InMemoryGroupVariantRepository,
     *     accessoires: InMemoryAccessoireRepository,
     *     afas: InMemoryAfasSamenstellingenRepository,
     *     lookup: AfasSamenstellingLookup
     * }
     */
    private function emptyBag(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $baseItems = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $wiper = new class () implements ToolDataWiper {
            public function wipe(): void
            {
            }
        };

        return [
            'wiper' => $wiper,
            'groups' => $groups,
            'bases' => $bases,
            'baseItems' => $baseItems,
            'variants' => $variants,
            'accessoires' => $accessoires,
            'afas' => $afas,
            'lookup' => new AfasSamenstellingLookup($afas),
        ];
    }

    /**
     * @param list<PortalCsvRow> $rows
     */
    private function reader(array $rows): PortalCsvReader
    {
        return new class ($rows) implements PortalCsvReader {
            /** @param list<PortalCsvRow> $rows */
            public function __construct(private array $rows)
            {
            }

            public function read(string $path): iterable
            {
                yield from $this->rows;
            }
        };
    }
}
