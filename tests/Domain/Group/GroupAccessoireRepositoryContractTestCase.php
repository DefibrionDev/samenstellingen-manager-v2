<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Group;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class GroupAccessoireRepositoryContractTestCase extends TestCase
{
    /**
     * @return array{groups: GroupRepository, accessoires: AccessoireRepository, links: GroupAccessoireRepository}
     */
    abstract protected function makeRepositories(): array;

    private GroupRepository $groups;
    private AccessoireRepository $accessoires;
    private GroupAccessoireRepository $links;

    protected function setUp(): void
    {
        $repos = $this->makeRepositories();
        $this->groups = $repos['groups'];
        $this->accessoires = $repos['accessoires'];
        $this->links = $repos['links'];

        $this->groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $this->accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
    }

    #[Test]
    public function linksAndRetrievesForGroup(): void
    {
        $this->links->link('52112', '60112');

        $found = $this->links->findAllForGroup('52112');

        self::assertCount(1, $found);
        self::assertSame('60112', $found[0]->itemcode);
    }

    #[Test]
    public function returnsEmptyListForGroupWithoutLinks(): void
    {
        self::assertSame([], $this->links->findAllForGroup('52112'));
    }

    #[Test]
    public function rejectsDuplicateLink(): void
    {
        $this->links->link('52112', '60112');

        $this->expectException(AccessoireAlreadyLinkedException::class);

        $this->links->link('52112', '60112');
    }

    #[Test]
    public function rejectsLinkForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->links->link('99999', '60112');
    }

    #[Test]
    public function rejectsLinkForUnknownAccessoire(): void
    {
        $this->expectException(AccessoireNotFoundException::class);

        $this->links->link('52112', '99999');
    }

    #[Test]
    public function rejectsFindForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->links->findAllForGroup('99999');
    }
}
