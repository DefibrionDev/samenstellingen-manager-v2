<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Publications;

use Defibrion\Samenstellingen\Application\Publications\ListOnlineNotAssignedHandler;
use Defibrion\Samenstellingen\Application\Publications\SyncPublicationsHandler;
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

final class ListOnlineNotAssignedHandlerTest extends TestCase
{
    #[Test]
    public function returnsOnlineNotAssignedRowsFromDryRunComparison(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $websites = new InMemoryWebsiteRepository();
        $publications = new InMemoryBasePublicationRepository();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));
        $afas->replaceSnapshot([new AfasSamenstelling('11111', 'Base', '10013', [])]);
        $wNl = $websites->save(new Website(null, 'Reseller NL', 'U_NL_SYNC', 'U_NL_TONEN'));
        $websites->save(new Website(null, 'ARKY', 'U_ARKY_SYNC', 'U_ARKY_TONEN'));
        self::assertNotNull($base->id);
        self::assertNotNull($wNl->id);
        $publications->setPublished($base->id, $wNl->id, true); // alleen NL toegekend

        // Snapshot: 11111 staat online op ARKY (sync) — niet toegekend in de tool.
        $reader = new InMemoryAfasFreeFieldStateReader(['11111' => ['U_ARKY_SYNC' => true]]);
        $sync = new SyncPublicationsHandler($groups, $bases, $links, $afas, $websites, $publications, new InMemoryPublicationSyncWriter(), $reader);

        $rows = (new ListOnlineNotAssignedHandler($sync))();

        self::assertCount(1, $rows);
        self::assertSame('11111', $rows[0]->afasItemcode);
        self::assertSame('ARKY', $rows[0]->websiteName);
    }
}
