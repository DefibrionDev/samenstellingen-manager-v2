<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\ShowGroup;
use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShowGroupHandlerTest extends TestCase
{
    #[Test]
    public function returnsOverviewForExistingGroup(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $bases->saveForGroup('Reanibex 100 Semi-Auto', new GroupBase('50013', 'NL', 'AED NL'));
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $links->link('Reanibex 100 Semi-Auto', '60112');

        $handler = new ShowGroupHandler($groups, $bases, $links);
        $overview = $handler(new ShowGroup('Reanibex 100 Semi-Auto'));

        self::assertSame('Reanibex 100 Semi-Auto', $overview->group->name);
        self::assertSame('52112', $overview->group->familyHeadItemcode);
        self::assertCount(1, $overview->bases);
        self::assertSame('50013', $overview->bases[0]->itemcode);
        self::assertCount(1, $overview->accessoires);
        self::assertSame('60112', $overview->accessoires[0]->itemcode);
    }

    #[Test]
    public function returnsOverviewForGroupWithoutBasesOrAccessoires(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);

        $handler = new ShowGroupHandler($groups, $bases, $links);
        $overview = $handler(new ShowGroup('Reanibex 100 Semi-Auto'));

        self::assertSame([], $overview->bases);
        self::assertSame([], $overview->accessoires);
    }

    #[Test]
    public function throwsWhenGroupNotFound(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $handler = new ShowGroupHandler($groups, $bases, $links);

        $this->expectException(GroupNotFoundException::class);
        $this->expectExceptionMessage("Groep 'Onbekend' niet gevonden");

        $handler(new ShowGroup('Onbekend'));
    }
}
