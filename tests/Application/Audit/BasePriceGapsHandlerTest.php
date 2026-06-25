<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\BasePriceGapsHandler;
use Defibrion\Samenstellingen\Application\Audit\ListBasePriceGaps;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstWhitelistRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BasePriceGapsHandlerTest extends TestCase
{
    #[Test]
    public function emptyWhenNoBases(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Leeg', '99999'));

        self::assertSame([], ($bag['handler'])(new ListBasePriceGaps()));
    }

    #[Test]
    public function reportsGapWhenBaseMissingFromWhitelistedPrijslijst(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));

        // Base heeft alleen een prijs in *****; in de óók-whitelisted lijst 003 ontbreekt-ie.
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new ListBasePriceGaps());

        self::assertCount(1, $rows);
        self::assertSame('003', $rows[0]->prijslijstId);
        self::assertSame('Dealers FR', $rows[0]->prijslijstOmschrijving);
        self::assertSame('11142', $rows[0]->baseAfasItemcode);
        self::assertSame('Reanibex', $rows[0]->groupName);
        self::assertSame('Base NL', $rows[0]->baseName);
    }

    #[Test]
    public function noGapWhenBaseHasPriceInAllWhitelistedLists(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));

        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142', '003', null, 169900, null, '2025-01-01', null),
        ]);

        self::assertSame([], ($bag['handler'])(new ListBasePriceGaps()));
    }

    #[Test]
    public function ignoresNonWhitelistedPrijslijsten(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));

        // Base staat in beide whitelisted lijsten; ontbreekt alleen in de niet-whitelisted 999.
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142', '003', null, 169900, null, '2025-01-01', null),
        ]);

        self::assertSame([], ($bag['handler'])(new ListBasePriceGaps()));
    }

    #[Test]
    public function clientOnlyPriceDoesNotCountAsCoverage(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));

        // Alleen een klant-specifieke prijs (debiteur_id gevuld) in 003 → 003 telt als ontbrekend.
        $bag['prijzen']->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142', '003', 'DEB001', 169900, null, '2025-01-01', null),
        ]);

        $rows = ($bag['handler'])(new ListBasePriceGaps());

        self::assertCount(1, $rows);
        self::assertSame('003', $rows[0]->prijslijstId);
    }

    #[Test]
    public function skipsBaseWithoutItemcode(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base zonder code', 'NL', null));

        self::assertSame([], ($bag['handler'])(new ListBasePriceGaps()));
    }

    #[Test]
    public function nullOmschrijvingWhenPrijslijstUnknown(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $bag['bases']->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));

        // Prijslijst "888" is whitelisted maar zit niet in de prijslijsten-snapshot.
        $bag['whitelist']->add('888', 'test');

        $rows = ($bag['handler'])(new ListBasePriceGaps());

        $row888 = array_values(array_filter($rows, static fn ($r) => $r->prijslijstId === '888'));
        self::assertCount(1, $row888);
        self::assertNull($row888[0]->prijslijstOmschrijving);
    }

    /**
     * @return array{
     *   groups: InMemoryGroupRepository,
     *   bases: InMemoryGroupBaseRepository,
     *   prijzen: InMemoryAfasPrijsRepository,
     *   prijslijsten: InMemoryAfasPrijslijstRepository,
     *   whitelist: InMemoryPrijslijstWhitelistRepository,
     *   handler: BasePriceGapsHandler
     * }
     */
    private function wiring(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $prijzen = new InMemoryAfasPrijsRepository();
        $prijslijsten = new InMemoryAfasPrijslijstRepository();
        $prijslijsten->replaceSnapshot([
            new AfasPrijslijst('*****', 'Basisprijslijst (excl BTW)'),
            new AfasPrijslijst('003', 'Dealers FR'),
        ]);
        $whitelist = new InMemoryPrijslijstWhitelistRepository();
        foreach (['*****', '003'] as $id) {
            $whitelist->add($id, 'test');
        }
        $handler = new BasePriceGapsHandler($groups, $bases, $prijzen, $prijslijsten, $whitelist);

        return compact('groups', 'bases', 'prijzen', 'prijslijsten', 'whitelist', 'handler');
    }
}
