<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Publications;

use Defibrion\Samenstellingen\Application\Publications\SyncPublications;
use Defibrion\Samenstellingen\Application\Publications\SyncPublicationsHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBasePublicationRepository;
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
        sort($codes);
        self::assertSame(['11111', '11111-60110', '11111-60112'], $codes);

        // Flags: Reseller NL gepubliceerd → sync+tonen true; Reseller FR niet → false
        $flags = $result->plans[0]->freeFieldFlags;
        self::assertTrue($flags['U_NL_SYNC']);
        self::assertTrue($flags['U_NL_TONEN']);
        self::assertFalse($flags['U_FR_SYNC']);
        self::assertFalse($flags['U_FR_TONEN']);
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
     * @return array{handler: SyncPublicationsHandler, writer: InMemoryPublicationSyncWriter}
     */
    private function makeWiring(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        $wFr = $websites->save(new Website(null, 'Reseller FR', 'U_FR_SYNC', 'U_FR_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        self::assertNotNull($wFr->id);
        $publications->setPublished($base->id, $wNl->id, true);
        // Reseller FR: geen rij → wordt impliciet behandeld als not published

        $afas->replaceSnapshot([
            new AfasSamenstelling('11111', 'Base', '10013', []),
            new AfasSamenstelling('11111-60110', 'Variant 1', '11111', []),
            new AfasSamenstelling('11111-60112', 'Variant 2', '11111', []),
            new AfasSamenstelling('99999', 'Andere groep', null, []),
        ]);

        $afasState = new InMemoryAfasFreeFieldStateReader([]);
        $handler = new SyncPublicationsHandler($groups, $bases, $afas, $websites, $publications, $writer, $afasState);

        return ['handler' => $handler, 'writer' => $writer];
    }

    #[Test]
    public function skipsItemcodesWhereAfasAlreadyMatchesDesiredState(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();
        $writer = new InMemoryPublicationSyncWriter();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($base->id, $wNl->id, true);

        $afas->replaceSnapshot([
            new AfasSamenstelling('11111', 'Base', '10013', []),
            new AfasSamenstelling('11111-60110', 'Variant 1', '11111', []),
        ]);

        // AFAS heeft beide al op true voor 11111 (matched) maar niet voor 11111-60110 (mismatch).
        $afasState = new InMemoryAfasFreeFieldStateReader([
            '11111' => ['U_NL_SYNC' => true, 'U_NL_TONEN' => true],
            '11111-60110' => ['U_NL_SYNC' => false, 'U_NL_TONEN' => true],
        ]);

        $handler = new SyncPublicationsHandler($groups, $bases, $afas, $websites, $publications, $writer, $afasState);

        $result = $handler(new SyncPublications(apply: true));

        // Alleen 11111-60110 mismatched → 1 plan, 1 applied.
        self::assertCount(1, $result->plans);
        self::assertSame('11111-60110', $result->plans[0]->afasItemcode);
        self::assertSame(1, $result->appliedCount);
    }
}
