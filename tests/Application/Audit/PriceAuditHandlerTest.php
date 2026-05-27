<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditPrices;
use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstWhitelistRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PriceAuditHandlerTest extends TestCase
{
    #[Test]
    public function emptyWhenNothingToCheck(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('X', '99999'));

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertSame([], $rows);
    }

    #[Test]
    public function reportsNoDriftWhenDeltaMatches(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        // Basis-prijslijst: base €1899, variant €1924 → delta €25 = match
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 192400, null, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertSame([], $rows);
    }

    #[Test]
    public function reportsToeslagDriftWhenDeltaWrong(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        // base €1899, variant €1930 → actual delta €31, expected €25 → drift
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 193000, null, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertCount(1, $rows);
        self::assertSame('toeslag-drift', $rows[0]->status);
        self::assertSame(2500, $rows[0]->expectedDeltaCents);
        self::assertSame(3100, $rows[0]->actualDeltaCents);
        self::assertSame('11142-60110', $rows[0]->variantAfasItemcode);
        self::assertSame('Basisprijslijst (excl BTW)', $rows[0]->prijslijstOmschrijving);
    }

    #[Test]
    public function onlyIncludesWhitelistedPrijslijstenForBothStatuses(): void
    {
        // Maak een handler met lege whitelist om whitelist-gedrag te testen.
        $bag = $this->wiring();
        $emptyWhitelist = new InMemoryPrijslijstWhitelistRepository();
        $handler = new PriceAuditHandler(
            $bag['groups'],
            $bag['bases'],
            $bag['links'],
            $bag['prijzen'],
            $bag['prijslijsten'],
            $emptyWhitelist,
        );

        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        // Twee prijslijsten: ***** met drift, 003 met missing.
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 193000, null, '2025-01-01', null), // drift
            new AfasPrijs('11142', '003', null, 169900, null, '2025-01-01', null),
            // variant ontbreekt in 003 → missing
        ]);

        // Lege whitelist: niets in audit.
        self::assertSame([], $handler(new AuditPrices()));

        // ***** op whitelist: drift-rij voor ***** komt door.
        $emptyWhitelist->add('*****', 'test');
        $rows = $handler(new AuditPrices());
        $ids = array_map(static fn ($r) => $r->prijslijstId, $rows);
        self::assertSame(['*****'], $ids);

        // Beide op whitelist: 2 rijen.
        $emptyWhitelist->add('003', 'test');
        self::assertCount(2, $handler(new AuditPrices()));
    }

    #[Test]
    public function returnsNullOmschrijvingWhenPrijslijstUnknown(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        // Prijslijst "888" zit niet in de prijslijsten-snapshot, maar wel in whitelist.
        $bag['whitelist']->add('888', 'test');
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '888', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '888', null, 193000, null, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertCount(1, $rows);
        self::assertNull($rows[0]->prijslijstOmschrijving);
    }

    #[Test]
    public function reportsMissingWhenVariantHasNoPrice(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        // Base heeft prijs in twee lijsten, variant alleen in één
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142', '003', null, 169900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 192400, null, '2025-01-01', null),
            // Variant ontbreekt in 003
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertCount(1, $rows);
        self::assertSame('missing', $rows[0]->status);
        self::assertSame('003', $rows[0]->prijslijstId);
    }

    #[Test]
    public function auditsHigherStaffelsSeparately(): void
    {
        // Plat model: zelfde delta (€25) op elke staffel. Variant op staffel=10 wijkt
        // af van base+delta → drift voor die staffel.
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        $bag['prijzen']->replaceSnapshot([
            // Baseline: base €1899, variant €1929 → actual delta €30, expected €25 → drift
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 192900, null, '2025-01-01', null),
            // Staffel 10: base €1799, variant €1850 → actual delta €51, expected €25 → drift
            new AfasPrijs('11142', '*****', null, 179900, 10, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 185000, 10, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertCount(2, $rows);
        $perStaffel = [];
        foreach ($rows as $r) {
            $perStaffel[$r->staffelAantal ?? 0] = $r;
        }
        self::assertSame('toeslag-drift', $perStaffel[0]->status);
        self::assertSame(3000, $perStaffel[0]->actualDeltaCents);
        self::assertSame('toeslag-drift', $perStaffel[10]->status);
        self::assertSame(5100, $perStaffel[10]->actualDeltaCents);
    }

    #[Test]
    public function reportsMissingOnHigherStaffelWhenBaseHasItButVariantDoesnt(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        $bag['prijzen']->replaceSnapshot([
            // Baseline klopt voor beide
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 192400, null, '2025-01-01', null),
            // Base heeft staffel 10, variant niet
            new AfasPrijs('11142', '*****', null, 179900, 10, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertCount(1, $rows);
        self::assertSame('missing', $rows[0]->status);
        self::assertSame(10, $rows[0]->staffelAantal);
    }

    #[Test]
    public function reportsInconsistentStaffelWhenVariantHasItButBaseDoesnt(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        $bag['prijzen']->replaceSnapshot([
            // Baseline klopt voor beide
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 192400, null, '2025-01-01', null),
            // Variant heeft staffel 10, base niet
            new AfasPrijs('11142-60110', '*****', null, 185000, 10, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertCount(1, $rows);
        self::assertSame('inconsistent-staffel', $rows[0]->status);
        self::assertSame(10, $rows[0]->staffelAantal);
        self::assertNull($rows[0]->basePrijsCents);
        self::assertSame(185000, $rows[0]->variantPrijsCents);
    }

    #[Test]
    public function skipsClientSpecificPrices(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['accessoires']->save(new Accessoire('60110', 'Rugzak', 2500));
        $bag['links']->link('52112', '60110');

        // Klant-specifieke base-prijs (debiteur_id = X) telt niet mee in audit
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', 'DEB001', 100000, null, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new AuditPrices());

        self::assertSame([], $rows);
    }

    /**
     * @return array{
     *   groups: InMemoryGroupRepository,
     *   bases: InMemoryGroupBaseRepository,
     *   accessoires: InMemoryAccessoireRepository,
     *   links: InMemoryGroupAccessoireRepository,
     *   prijzen: InMemoryAfasPrijsRepository,
     *   prijslijsten: InMemoryAfasPrijslijstRepository,
     *   whitelist: InMemoryPrijslijstWhitelistRepository,
     *   handler: PriceAuditHandler
     * }
     */
    private function wiring(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $prijzen = new InMemoryAfasPrijsRepository();
        $prijslijsten = new InMemoryAfasPrijslijstRepository();
        $prijslijsten->replaceSnapshot([
            new AfasPrijslijst('*****', 'Basisprijslijst (excl BTW)'),
            new AfasPrijslijst('003', 'Dealers FR'),
        ]);
        $whitelist = new InMemoryPrijslijstWhitelistRepository();
        // Default whitelist 2 bekende lijsten zodat bestaande tests werken.
        // Tests die whitelist-gedrag verifiëren maken bewust een nieuwe handler.
        foreach (['*****', '003'] as $id) {
            $whitelist->add($id, 'test');
        }
        $handler = new PriceAuditHandler($groups, $bases, $links, $prijzen, $prijslijsten, $whitelist);

        return compact('groups', 'bases', 'accessoires', 'links', 'prijzen', 'prijslijsten', 'whitelist', 'handler');
    }
}
