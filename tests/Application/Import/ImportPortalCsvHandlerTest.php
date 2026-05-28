<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Import;

use Defibrion\Samenstellingen\Application\Import\ImportPortalCsv;
use Defibrion\Samenstellingen\Application\Import\ImportPortalCsvHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingLookup;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Import\PortalCsvReader;
use Defibrion\Samenstellingen\Domain\Import\PortalCsvRow;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBomBlacklistRepository;
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

        $handler = $this->makeHandler($bag, $reader);

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
    public function blacklistedBomCodeRemovesCandidateFromAmbiguity(): void
    {
        // Twee bases voor article 50013: 11132 met FR-stickerset 81211, 11135 met WAL 81311.
        // Zonder blacklist zou de import ambigu rapporteren. Met 81311 op de blacklist
        // blijft alleen 11132 over en slaagt de import.
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['blacklist']->save(new BomBlacklistEntry('81311', 'Waalse stickerset — niet de basis-taal'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11132', 'AED Pakket FR', null, ['50013', '70112', '81211']),
            new AfasSamenstelling('11135', 'AED Pakket WAL', null, ['50013', '70112', '81311']),
        ]);

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Heartsine Samaritan PAD 500P', 'AED FR', '', 'FR'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertSame([], $summary->unresolved);
        self::assertSame(1, $summary->basesCreated);
    }

    #[Test]
    public function importsResolvableRowsAlongsideUnresolvedReport(): void
    {
        // Twee rijen in twee groepen: één resolveerbaar, één ambigu.
        // Verwacht: resolveerbare wordt geïmporteerd, ambigue blijft in rapport.
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            // Resolveerbaar: één unieke base voor article 50013.
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('11142-60110', 'Variant met rugzak', '11142', ['50013', '60110', '70112', '81111']),
            // Ambigu: twee bases voor article 60013.
            new AfasSamenstelling('12001', 'Andere AED NL', null, ['60013', '70112', '81111']),
            new AfasSamenstelling('12002', 'Andere AED NL alt', null, ['60013', '70112', '81111', '90099']),
        ]);

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Resolveerbaar', 'AED NL', '', 'NL'),
            new PortalCsvRow('60013', 'Ambigu', 'AED NL', '', 'NL'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertCount(1, $summary->unresolved);
        self::assertSame('60013', $summary->unresolved[0]['code']);
        self::assertStringContainsString('Ambigu', $summary->unresolved[0]['reason']);
        // De resolveerbare rij is wél geïmporteerd ondanks de ambigue tweede rij.
        self::assertSame(1, $summary->groupsCreated);
        self::assertSame(1, $summary->basesCreated);
    }

    #[Test]
    public function storesAfasItemcodeOnBase(): void
    {
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
        ]);

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex 100 Semi-Auto', 'AED NL', '', 'NL'),
        ]);

        $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        $bases = $bag['bases']->findAllForGroup('11142');
        self::assertCount(1, $bases);
        self::assertSame('11142', $bases[0]->afasItemcode);
    }

    #[Test]
    public function secondImportIsIdempotentAndPreservesUserDefinedConfig(): void
    {
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
        ]);
        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex 100 Semi-Auto', 'AED NL', '', 'NL'),
        ]);

        // 1) Eerste import — groep + base aangemaakt.
        $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        // 2) User-defined config bovenop de geïmporteerde groep:
        //    a) model_name op de groep (slice 18 seed)
        //    b) accessoire-koppeling
        $existing = $bag['groups']->findByFamilyHeadItemcode('11142');
        self::assertNotNull($existing);
        // InMemoryGroupRepository: vervang via opnieuw save met model_name.
        // (Simuleert wat slice 18's SQL UPDATE deed.)
        $bag['groups']->delete('11142');
        $bag['groups']->save(new \Defibrion\Samenstellingen\Domain\Group\Group(
            $existing->name,
            $existing->familyHeadItemcode,
            'Reanibex 100 semi-automaat',
        ));
        $bag['links']->link('11142', '60110');

        // Baseline na seed: 1 koppeling, model_name gevuld.
        self::assertCount(1, $bag['links']->findAllForGroup('11142'));
        $afterSeed = $bag['groups']->findByFamilyHeadItemcode('11142');
        self::assertNotNull($afterSeed);
        self::assertSame('Reanibex 100 semi-automaat', $afterSeed->modelNameNl);

        // 3) Tweede import — identieke CSV — moet idempotent zijn.
        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertSame(0, $summary->groupsCreated, 'Geen nieuwe groep — bestaande wordt herkend');
        self::assertSame(0, $summary->basesCreated, 'Geen nieuwe base — bestaande wordt herkend');
        // model_name moet overleven
        $afterReimport = $bag['groups']->findByFamilyHeadItemcode('11142');
        self::assertNotNull($afterReimport);
        self::assertSame(
            'Reanibex 100 semi-automaat',
            $afterReimport->modelNameNl,
            'model_name moet behouden blijven over herimport',
        );
        // accessoire-koppeling moet overleven
        self::assertCount(
            1,
            $bag['links']->findAllForGroup('11142'),
            'group_accessoires-koppeling moet behouden blijven over herimport'
        );
    }

    #[Test]
    public function removesGroupsThatNoLongerAppearInCsv(): void
    {
        // Eerste import zet twee groepen op.
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('22222', 'Andere AED', null, ['60013', '70112', '81111']),
        ]);
        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex', 'AED NL', '', 'NL'),
            new PortalCsvRow('60013', 'Andere groep', 'AED NL', '', 'NL'),
        ]);
        $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));
        self::assertCount(2, $bag['groups']->findAll());

        // Tweede import — CSV bevat alleen de eerste groep nog.
        $shrunkReader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex', 'AED NL', '', 'NL'),
        ]);
        $this->makeHandler($bag, $shrunkReader)(new ImportPortalCsv('/irrelevant.csv'));

        // 'Andere groep' is weggevallen → opgeruimd.
        self::assertCount(1, $bag['groups']->findAll());
        self::assertNotNull($bag['groups']->findByFamilyHeadItemcode('11142'));
        self::assertNull($bag['groups']->findByFamilyHeadItemcode('22222'));
    }

    #[Test]
    public function rejectsEnglishBaseWithoutStickerset(): void
    {
        // EN moet sinds eind mei 2026 ook stickerset hebben (oude uitzondering vervallen).
        // UK is in onze tool een markt, geen taal — taal-code is EN.
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11100', 'Zoll AED Plus EN', null, ['11000', '70112']), // geen 81xxx
        ]);
        $reader = $this->reader([
            new PortalCsvRow('11000', 'Zoll', 'AED EN', '', 'EN'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertCount(1, $summary->unresolved);
        self::assertSame('11000', $summary->unresolved[0]['code']);
    }

    #[Test]
    public function stillRejectsNonEnglishBaseWithoutStickerset(): void
    {
        // NL-bases MOETEN sticker hebben — anders blijven we ze als incompleet zien.
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11100', 'Iets NL', null, ['11000', '70112']), // geen 81xxx
        ]);
        $reader = $this->reader([
            new PortalCsvRow('11000', 'Zoll', 'AED NL', '', 'NL'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertCount(1, $summary->unresolved);
        self::assertSame('11000', $summary->unresolved[0]['code']);
        self::assertStringContainsString('Geen base', $summary->unresolved[0]['reason']);
    }

    #[Test]
    public function compoundLanguageStillRequiresStickerset(): void
    {
        // NL/EN-compound bases hebben in AFAS gewoon de NL-stickerset → vereisten blijven.
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            // Met sticker (correct compound) — moet accepteren
            new AfasSamenstelling('11142', 'AED pakket NL/EN', null, ['50013', '70112', '81111']),
            // Zonder sticker — moet afwijzen, want compound is geen pure EN
            new AfasSamenstelling('22222', 'Iets NL/EN zonder sticker', null, ['60013', '70112']),
        ]);
        $reader = $this->reader([
            new PortalCsvRow('50013', 'Lifepak CR2', 'AED NL/EN', '', 'NL/EN'),
            new PortalCsvRow('60013', 'Iets anders', 'AED NL/EN', '', 'NL/EN'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        // 11142 wordt geïmporteerd; 22222 belandt in unresolved want geen sticker bij compound
        self::assertSame(1, $summary->basesCreated);
        self::assertCount(1, $summary->unresolved);
        self::assertSame('60013', $summary->unresolved[0]['code']);
    }

    #[Test]
    public function pinnedBaseResolvesAmbiguity(): void
    {
        // Twee kandidaten in AFAS-snapshot voor article 50013, dus normaal "ambigu".
        // Maar de user heeft al een base in de tool die SKU 11142 expliciet kiest
        // (via group:add-base-from-afas). De prevalidate moet die keuze respecteren
        // en niet meer als ambigu melden.
        $bag = $this->emptyBag();
        $bag['accessoires']->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('11145', 'AED pakket NL alt', null, ['50013', '70112', '81111', '90099']),
        ]);
        $bag['groups']->save(new \Defibrion\Samenstellingen\Domain\Group\Group('Reanibex', '11142'));
        $bag['bases']->saveForGroup('11142', new \Defibrion\Samenstellingen\Domain\Group\GroupBase(null, 'Handmatig gepind', 'NL', '11142'));

        $reader = $this->reader([
            new PortalCsvRow('50013', 'Reanibex', 'AED NL', '', 'NL'),
        ]);

        $summary = $this->makeHandler($bag, $reader)(new ImportPortalCsv('/irrelevant.csv'));

        self::assertSame([], $summary->unresolved, 'Ambiguïteit moet opgelost zijn door pinned SKU 11142');
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
        $syncHandler = new \Defibrion\Samenstellingen\Application\Group\SyncGroupAgainstAfasHandler(
            $bag['variants'],
            $bag['baseItems'],
            $bag['afas'],
            new \Defibrion\Samenstellingen\Domain\Afas\VariantMatcher(),
        );
        $syncAll = new \Defibrion\Samenstellingen\Application\Group\SyncAllGroupsHandler(
            $bag['groups'],
            $syncHandler,
            $bag['afas'],
        );

        return new ImportPortalCsvHandler(
            $reader,
            $bag['groups'],
            $bag['bases'],
            $bag['baseItems'],
            $bag['variants'],
            $bag['lookup'],
            $bag['accessoires'],
            $bag['blacklist'],
            $syncAll,
            $bag['articles'],
        );
    }

    /**
     * @return array{
     *     groups: InMemoryGroupRepository,
     *     bases: InMemoryGroupBaseRepository,
     *     baseItems: InMemoryGroupBaseItemRepository,
     *     variants: InMemoryGroupVariantRepository,
     *     accessoires: InMemoryAccessoireRepository,
     *     blacklist: InMemoryBomBlacklistRepository,
     *     links: InMemoryGroupAccessoireRepository,
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
        $blacklist = new InMemoryBomBlacklistRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $articles = new \Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasArticleRepository();

        return [
            'groups' => $groups,
            'bases' => $bases,
            'baseItems' => $baseItems,
            'variants' => $variants,
            'accessoires' => $accessoires,
            'links' => $links,
            'blacklist' => $blacklist,
            'afas' => $afas,
            'articles' => $articles,
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
