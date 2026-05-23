<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingLookup;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AfasSamenstellingLookupTest extends TestCase
{
    #[Test]
    public function findsBaseWithoutAnyAccessoireInBom(): void
    {
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
        ]);

        $bases = (new AfasSamenstellingLookup($repo))
            ->findCanonicalBasesContaining('50013', ['60110', '60112']);

        self::assertCount(1, $bases);
        self::assertSame('11142', $bases[0]->itemcode);
    }

    #[Test]
    public function filtersOutSamenstellingenContainingAnyAccessoire(): void
    {
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('11142-60110', 'AED pakket NL met buitenkast', '11142', ['50013', '60110', '70112', '81111']),
        ]);

        $bases = (new AfasSamenstellingLookup($repo))
            ->findCanonicalBasesContaining('50013', ['60110', '60112']);

        self::assertCount(1, $bases);
        self::assertSame('11142', $bases[0]->itemcode);
    }

    #[Test]
    public function acceptsLanguageSuffixedBaseWithoutAccessoire(): void
    {
        // Taal-suffix `-FR` lijkt op accessoire-suffix maar bevat geen accessoire-itemcode.
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot([
            new AfasSamenstelling('11142-FR', 'AED pakket FR', null, ['50013', '70112', '81211']),
        ]);

        $bases = (new AfasSamenstellingLookup($repo))
            ->findCanonicalBasesContaining('50013', ['60110', '60112']);

        self::assertCount(1, $bases);
        self::assertSame('11142-FR', $bases[0]->itemcode);
    }

    #[Test]
    public function returnsMultipleWhenAfasContainsAmbiguousBases(): void
    {
        // Twee distinct samenstellingen (verschillende BOM, geen duplicates) die beide
        // article 50013 bevatten en geen accessoire — een echte AFAS-ambiguïteit.
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('11145', 'AED pakket NL extra', null, ['50013', '70112', '81111', '90099']),
        ]);

        $bases = (new AfasSamenstellingLookup($repo))
            ->findCanonicalBasesContaining('50013', ['60110']);

        // Ambiguïteit is een verantwoordelijkheid van de aanroeper, niet van de lookup.
        self::assertCount(2, $bases);
    }

    #[Test]
    public function skipsDuplicates(): void
    {
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot([
            new AfasSamenstelling('11142', 'AED pakket NL', null, ['50013', '70112', '81111']),
            new AfasSamenstelling('11999', 'AED pakket NL (duplicaat)', null, ['50013', '70112', '81111']),
        ]);

        $bases = (new AfasSamenstellingLookup($repo))
            ->findCanonicalBasesContaining('50013', []);

        // DuplicateDetector wijst de hoogste itemcode als duplicaat aan → 1 canonical over.
        self::assertCount(1, $bases);
        self::assertSame('11142', $bases[0]->itemcode);
    }
}
