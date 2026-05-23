<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Group;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class GroupVariantRepositoryContractTestCase extends TestCase
{
    /**
     * @return array{
     *     groups: GroupRepository,
     *     bases: GroupBaseRepository,
     *     accessoires: AccessoireRepository,
     *     links: GroupAccessoireRepository,
     *     variants: GroupVariantRepository
     * }
     */
    abstract protected function makeRepositories(): array;

    private GroupRepository $groups;
    private GroupBaseRepository $bases;
    private AccessoireRepository $accessoires;
    private GroupAccessoireRepository $links;
    private GroupVariantRepository $variants;

    protected function setUp(): void
    {
        $repos = $this->makeRepositories();
        $this->groups = $repos['groups'];
        $this->bases = $repos['bases'];
        $this->accessoires = $repos['accessoires'];
        $this->links = $repos['links'];
        $this->variants = $repos['variants'];

        $this->groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
    }

    #[Test]
    public function returnsEmptyForGroupWithoutBases(): void
    {
        $this->variants->regenerateForGroup('52112');

        self::assertSame([], $this->variants->findAllForGroup('52112'));
    }

    #[Test]
    public function generatesBaseOnlyVariantsWhenNoAccessoires(): void
    {
        $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        $this->bases->saveForGroup('52112', new GroupBase(null, 'Pack DAE FR', 'FR'));

        $this->variants->regenerateForGroup('52112');

        $result = $this->variants->findAllForGroup('52112');
        self::assertCount(2, $result);
        foreach ($result as $variant) {
            self::assertNull($variant->accessoireItemcode);
        }
    }

    #[Test]
    public function generatesFullMatrix(): void
    {
        $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        $this->bases->saveForGroup('52112', new GroupBase(null, 'Pack DAE FR', 'FR'));
        $this->accessoires->save(new Accessoire('60110', 'Rugzak'));
        $this->accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $this->links->link('52112', '60110');
        $this->links->link('52112', '60112');

        $this->variants->regenerateForGroup('52112');

        $result = $this->variants->findAllForGroup('52112');
        self::assertCount(6, $result, '2 bases × (geen + 2 accessoires) = 6 varianten');
    }

    #[Test]
    public function regenerationIsIdempotent(): void
    {
        $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        $this->accessoires->save(new Accessoire('60110', 'Rugzak'));
        $this->links->link('52112', '60110');

        $this->variants->regenerateForGroup('52112');
        $this->variants->regenerateForGroup('52112');
        $this->variants->regenerateForGroup('52112');

        self::assertCount(2, $this->variants->findAllForGroup('52112'));
    }

    #[Test]
    public function rejectsRegenerateForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->variants->regenerateForGroup('99999');
    }

    #[Test]
    public function rejectsFindForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->variants->findAllForGroup('99999');
    }
}
