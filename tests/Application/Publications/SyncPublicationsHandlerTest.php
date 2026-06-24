<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Publications;

use Defibrion\Samenstellingen\Application\Publications\SyncPublications;
use Defibrion\Samenstellingen\Application\Publications\SyncPublicationsHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBasePublicationRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWebsiteRepository;
use Defibrion\Samenstellingen\Infrastructure\Publications\InMemoryAfasFreeFieldStateReader;
use Defibrion\Samenstellingen\Infrastructure\Publications\InMemoryPublicationSyncWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyncPublicationsHandlerTest extends TestCase
{
    #[Test]
    public function plansBaseAndAccessoireVariantsWithFlags(): void
    {
        $bag = $this->makeWiring();

        $result = ($bag['handler'])(new SyncPublications(apply: false));

        // Verwacht: base 11111 + 2 accessoire-varianten (11111-60110, 11111-60112) = 3 plans
        self::assertCount(3, $result->plans);
        $codes = array_map(static fn ($p) => $p->afasItemcode, $result->plans);
        sort($codes, SORT_STRING);
        self::assertSame(['11111', '11111-60110', '11111-60112'], $codes);

        // Additief: Reseller NL gepubliceerd → sync+tonen in de payload (true); Reseller FR
        // niet gepubliceerd → NIET in de payload, zodat de PUT 'm nooit kan uitzetten.
        $flags = $result->plans[0]->freeFieldFlags;
        self::assertTrue($flags['U_NL_SYNC']);
        self::assertTrue($flags['U_NL_TONEN']);
        self::assertArrayNotHasKey('U_FR_SYNC', $flags);
        self::assertArrayNotHasKey('U_FR_TONEN', $flags);
    }

    #[Test]
    public function dryRunDoesNotInvokeWriter(): void
    {
        $bag = $this->makeWiring();

        $result = ($bag['handler'])(new SyncPublications(apply: false));

        self::assertSame(0, $result->appliedCount);
        self::assertSame([], $bag['writer']->applied);
        self::assertNotEmpty($result->plans);
    }

    #[Test]
    public function applyInvokesWriterPerPlan(): void
    {
        $bag = $this->makeWiring();

        $result = ($bag['handler'])(new SyncPublications(apply: true));

        self::assertSame(3, $result->appliedCount);
        self::assertCount(3, $bag['writer']->applied);
    }

    #[Test]
    public function limitTruncatesPlans(): void
    {
        $bag = $this->makeWiring();

        $result = ($bag['handler'])(new SyncPublications(apply: false, limit: 2));

        self::assertCount(2, $result->plans);
    }

    /**
     * Standaard happy-path wiring: 1 base met 2 accessoires, beide variant-itemcodes
     * bestaan in AFAS-snapshot, base is published op Reseller NL maar niet op FR.
     *
     * @return array{handler: SyncPublicationsHandler, writer: InMemoryPublicationSyncWriter}
     */
    private function makeWiring(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links->link('10013', '60110');
        $links->link('10013', '60112');
        $afas->replaceSnapshot([
            new AfasSamenstelling('11111', 'Base', '10013', []),
            new AfasSamenstelling('11111-60110', 'Variant 1', '11111', []),
            new AfasSamenstelling('11111-60112', 'Variant 2', '11111', []),
        ]);

        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        $wFr = $websites->save(new Website(null, 'Reseller FR', 'U_FR_SYNC', 'U_FR_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        self::assertNotNull($wFr->id);
        $publications->setPublished($base->id, $wNl->id, true);
        // Reseller FR: geen rij → wordt impliciet behandeld als not published

        $afasState = new InMemoryAfasFreeFieldStateReader([]);
        $handler = new SyncPublicationsHandler($groups, $bases, $links, $afas, $websites, $publications, $writer, $afasState);

        return ['handler' => $handler, 'writer' => $writer];
    }

    #[Test]
    public function skipsItemcodesWhereAfasAlreadyMatchesDesiredState(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10013', '60110');
        $afas->replaceSnapshot([
            new AfasSamenstelling('11111', 'Base', '10013', []),
            new AfasSamenstelling('11111-60110', 'Variant 1', '11111', []),
        ]);
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($base->id, $wNl->id, true);

        // AFAS heeft beide al op true voor 11111 (matched) maar niet voor 11111-60110 (mismatch).
        $afasState = new InMemoryAfasFreeFieldStateReader([
            '11111' => ['U_NL_SYNC' => true, 'U_NL_TONEN' => true],
            '11111-60110' => ['U_NL_SYNC' => false, 'U_NL_TONEN' => true],
        ]);

        $handler = new SyncPublicationsHandler($groups, $bases, $links, $afas, $websites, $publications, $writer, $afasState);

        $result = $handler(new SyncPublications(apply: true));

        // Alleen 11111-60110 mismatched → 1 plan, 1 applied.
        self::assertCount(1, $result->plans);
        self::assertSame('11111-60110', $result->plans[0]->afasItemcode);
        self::assertSame(1, $result->appliedCount);
    }

    #[Test]
    public function ignoresAfasItemcodesThatAreNotPartOfOurIntent(): void
    {
        // Prefix-collision-scenario: onze base 10144 met accessoire 60110.
        // AFAS bevat 10144 + 10144-60110 (onze intent) en óók 10144-CZ + 10144-CZ-60110
        // (taal-sibling, niet als base bij ons bekend). De target-iterator produceert
        // alleen onze intent-itemcodes — de CZ-strings ontstaan nooit uit base+accessoire.
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Philips HeartStart FRx', '10144'));
        $base = $bases->saveForGroup('10144', new GroupBase(null, 'NL', 'NL', '10144'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10144', '60110');
        $afas->replaceSnapshot([
            new AfasSamenstelling('10144', 'Base NL', '10144', []),
            new AfasSamenstelling('10144-60110', 'NL + Backpack', '10144', []),
            new AfasSamenstelling('10144-CZ', 'Base CZ taal-sibling', '10144', []),
            new AfasSamenstelling('10144-CZ-60110', 'CZ + Backpack taal-sibling', '10144', []),
        ]);
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($base->id, $wNl->id, true);

        $afasState = new InMemoryAfasFreeFieldStateReader([]);
        $handler = new SyncPublicationsHandler($groups, $bases, $links, $afas, $websites, $publications, $writer, $afasState);

        $result = $handler(new SyncPublications(apply: false));

        $codes = array_map(static fn ($p) => $p->afasItemcode, $result->plans);
        sort($codes, SORT_STRING);
        self::assertSame(['10144', '10144-60110'], $codes);
    }

    #[Test]
    public function siblingBasesWithSamePrefixAreScopedIndependently(): void
    {
        // Twee bases in onze DB met overlappende prefix (NL 10144 + DE 10144-DE),
        // beide met dezelfde 1 gelinkte accessoire. Elke base produceert alleen
        // z'n eigen targets; geen kruisbestuiving.
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Philips HeartStart FRx', '10144'));
        $baseNl = $bases->saveForGroup('10144', new GroupBase(null, 'NL', 'NL', '10144'));
        $baseDe = $bases->saveForGroup('10144', new GroupBase(null, 'DE', 'DE', '10144-DE'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10144', '60110');
        $afas->replaceSnapshot([
            new AfasSamenstelling('10144', 'Base NL', '10144', []),
            new AfasSamenstelling('10144-60110', 'NL + Backpack', '10144', []),
            new AfasSamenstelling('10144-DE', 'Base DE', '10144', []),
            new AfasSamenstelling('10144-DE-60110', 'DE + Backpack', '10144', []),
        ]);
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        self::assertNotNull($baseNl->id);
        self::assertNotNull($baseDe->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($baseNl->id, $wNl->id, true);
        $publications->setPublished($baseDe->id, $wNl->id, true);

        $afasState = new InMemoryAfasFreeFieldStateReader([]);
        $handler = new SyncPublicationsHandler($groups, $bases, $links, $afas, $websites, $publications, $writer, $afasState);

        $result = $handler(new SyncPublications(apply: false));

        $perBase = ['10144' => [], '10144-DE' => []];
        foreach ($result->plans as $plan) {
            $perBase[$plan->baseAfasItemcode][] = $plan->afasItemcode;
        }
        sort($perBase['10144'], SORT_STRING);
        sort($perBase['10144-DE'], SORT_STRING);

        self::assertSame(['10144', '10144-60110'], $perBase['10144']);
        self::assertSame(['10144-DE', '10144-DE-60110'], $perBase['10144-DE']);
    }

    #[Test]
    public function bucketAVariantIsTargetedEvenWhenAutoSyncDidNotLinkIt(): void
    {
        // Slice 49's gat: onze DB heeft intent (base + accessoire) maar de auto-sync
        // heeft de AFAS-itemcode niet aan een group_variants-rij gekoppeld (BOM-discrepantie).
        // Onder intent-derivation maakt dat niet uit — de plan-engine ziet de variant
        // wel zolang het verwachte itemcode `<base>-<accessoire>` in AFAS bestaat.
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Philips HeartStart FRx', '10144'));
        $base = $bases->saveForGroup('10144', new GroupBase(null, 'NL', 'NL', '10144'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10144', '60110');
        // GEEN $variants->markMatched-call: simuleert de slice-49-Philips-FRx-situatie.
        $afas->replaceSnapshot([
            new AfasSamenstelling('10144', 'Base NL', '10144', []),
            new AfasSamenstelling('10144-60110', 'NL + Backpack', '10144', []),
        ]);
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($base->id, $wNl->id, true);

        $afasState = new InMemoryAfasFreeFieldStateReader([]);
        $handler = new SyncPublicationsHandler($groups, $bases, $links, $afas, $websites, $publications, $writer, $afasState);

        $result = $handler(new SyncPublications(apply: false));

        $codes = array_map(static fn ($p) => $p->afasItemcode, $result->plans);
        sort($codes, SORT_STRING);
        self::assertSame(['10144', '10144-60110'], $codes);
    }

    #[Test]
    public function accessoireVariantIsSkippedWhenItemcodeMissingFromAfas(): void
    {
        // Onze DB linkt accessoire 60110 aan de groep, maar AFAS heeft het verwachte
        // variant-itemcode 11111-60110 (nog) niet — bv. omdat variants:fix-missing
        // nog niet is uitgevoerd. De target-iterator skipt het: we PUT'en geen flags
        // op een niet-bestaand itemcode (AFAS zou een 404 geven). Alleen de base
        // staat in targets.
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10013', '60110');
        $afas->replaceSnapshot([
            new AfasSamenstelling('11111', 'Base', '10013', []),
            // 11111-60110 ontbreekt
        ]);
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($base->id, $wNl->id, true);

        $afasState = new InMemoryAfasFreeFieldStateReader([]);
        $handler = new SyncPublicationsHandler($groups, $bases, $links, $afas, $websites, $publications, $writer, $afasState);

        $result = $handler(new SyncPublications(apply: false));

        self::assertCount(1, $result->plans);
        self::assertSame('11111', $result->plans[0]->afasItemcode);
    }

    #[Test]
    public function additiveSyncOnlyCarriesDesiredTrueFlags(): void
    {
        $bag = $this->makeWiringWithState([]); // AFAS leeg → NL onbekend → aanzetten

        $result = ($bag['handler'])(new SyncPublications(apply: false));

        self::assertCount(2, $result->plans);
        self::assertSame(['U_NL_SYNC' => true, 'U_NL_TONEN' => true], $result->plans[0]->freeFieldFlags);
        self::assertSame([], $result->onlineNotAssigned);
    }

    #[Test]
    public function reportsOnlineNotAssignedAndNeverTurnsOff(): void
    {
        // NL al aan in AFAS (matcht desired) + FR aan in AFAS (niet toegekend in de tool).
        $online = ['U_NL_SYNC' => true, 'U_NL_TONEN' => true, 'U_FR_SYNC' => true, 'U_FR_TONEN' => true];
        $bag = $this->makeWiringWithState(['11111' => $online, '11111-60110' => $online]);

        $result = ($bag['handler'])(new SyncPublications(apply: true));

        // Niets aan te zetten (NL al goed) → geen plans, writer niet aangeroepen → niets uitgezet.
        self::assertSame([], $result->plans);
        self::assertSame(0, $result->appliedCount);
        self::assertSame([], $bag['writer']->applied);
        // FR staat online maar is niet toegekend → meldingen voor beide itemcodes.
        self::assertCount(2, $result->onlineNotAssigned);
        $codes = array_map(static fn ($r) => $r->afasItemcode, $result->onlineNotAssigned);
        sort($codes, SORT_STRING);
        self::assertSame(['11111', '11111-60110'], $codes);
        self::assertSame('Reseller FR', $result->onlineNotAssigned[0]->websiteName);
    }

    #[Test]
    public function turnsOnMissingDesiredFlagButLeavesUnassignedOnlineFlagUntouched(): void
    {
        // FR aan in AFAS (niet toegekend), NL onbekend (moet aan).
        $state = ['U_FR_SYNC' => true, 'U_FR_TONEN' => true];
        $bag = $this->makeWiringWithState(['11111' => $state, '11111-60110' => $state]);

        $result = ($bag['handler'])(new SyncPublications(apply: false));

        self::assertCount(2, $result->plans);
        $flags = $result->plans[0]->freeFieldFlags;
        self::assertSame(['U_NL_SYNC' => true, 'U_NL_TONEN' => true], $flags); // alleen NL aan
        self::assertArrayNotHasKey('U_FR_SYNC', $flags);                       // FR niet aangeraakt
        self::assertCount(2, $result->onlineNotAssigned);                      // FR gemeld
    }

    /**
     * Wiring met 1 base (11111, NL gepubliceerd) + 1 accessoire (60110) en websites
     * Reseller NL + Reseller FR. AFAS-free-field-state injecteerbaar per test.
     *
     * @param array<int|string, array<string, bool>> $afasState (numerieke itemcode-keys worden door PHP naar int gecast)
     *
     * @return array{handler: SyncPublicationsHandler, writer: InMemoryPublicationSyncWriter}
     */
    private function makeWiringWithState(array $afasState): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10013', '60110');
        $afas->replaceSnapshot([
            new AfasSamenstelling('11111', 'Base', '10013', []),
            new AfasSamenstelling('11111-60110', 'Variant 1', '11111', []),
        ]);
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        $websites->save(new Website(null, 'Reseller FR', 'U_FR_SYNC', 'U_FR_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($base->id, $wNl->id, true);

        $handler = new SyncPublicationsHandler(
            $groups,
            $bases,
            $links,
            $afas,
            $websites,
            $publications,
            $writer,
            new InMemoryAfasFreeFieldStateReader($afasState),
        );

        return ['handler' => $handler, 'writer' => $writer];
    }
}
