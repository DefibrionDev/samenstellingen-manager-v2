<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroup;
use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddAccessoireToGroupHandlerTest extends TestCase
{
    #[Test]
    public function linksAccessoireToGroup(): void
    {
        [$groups, $accessoires, $links] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $handler = new AddAccessoireToGroupHandler($links);

        $handler(new AddAccessoireToGroup('Reanibex 100 Semi-Auto', '60112'));

        $found = $links->findAllForGroup('Reanibex 100 Semi-Auto');
        self::assertCount(1, $found);
        self::assertSame('60112', $found[0]->itemcode);
    }

    #[Test]
    public function passesThroughGroupNotFound(): void
    {
        [, , $links] = $this->repositories();
        $handler = new AddAccessoireToGroupHandler($links);

        $this->expectException(GroupNotFoundException::class);

        $handler(new AddAccessoireToGroup('Onbekend', '60112'));
    }

    #[Test]
    public function passesThroughAccessoireNotFound(): void
    {
        [$groups, , $links] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $handler = new AddAccessoireToGroupHandler($links);

        $this->expectException(AccessoireNotFoundException::class);

        $handler(new AddAccessoireToGroup('Reanibex 100 Semi-Auto', '99999'));
    }

    #[Test]
    public function passesThroughDuplicateLink(): void
    {
        [$groups, $accessoires, $links] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $handler = new AddAccessoireToGroupHandler($links);
        $handler(new AddAccessoireToGroup('Reanibex 100 Semi-Auto', '60112'));

        $this->expectException(AccessoireAlreadyLinkedException::class);

        $handler(new AddAccessoireToGroup('Reanibex 100 Semi-Auto', '60112'));
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryAccessoireRepository, 2: InMemoryGroupAccessoireRepository}
     */
    private function repositories(): array
    {
        $groups = new InMemoryGroupRepository();
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);

        return [$groups, $accessoires, $links];
    }
}
