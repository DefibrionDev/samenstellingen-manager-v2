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
        $this->variants->regenerateForGroup('Reanibex 100 Semi-Auto');

        self::assertSame([], $this->variants->findAllForGroup('Reanibex 100 Semi-Auto'));
    }

    #[Test]
    public function generatesBaseOnlyVariantsWhenNoAccessoires(): void
    {
        $this->bases->saveForGroup('Reanibex 100 Semi-Auto', new GroupBase('50013', 'NL', 'AED NL'));
        $this->bases->saveForGroup('Reanibex 100 Semi-Auto', new GroupBase('50001', 'FR', 'AED FR'));

        $this->variants->regenerateForGroup('Reanibex 100 Semi-Auto');

        $result = $this->variants->findAllForGroup('Reanibex 100 Semi-Auto');
        self::assertCount(2, $result);
        foreach ($result as $variant) {
            self::assertNull($variant->accessoireItemcode);
            self::assertTrue($variant->isBaseOnly());
        }
    }

    #[Test]
    public function generatesFullMatrix(): void
    {
        $this->bases->saveForGroup('Reanibex 100 Semi-Auto', new GroupBase('50013', 'NL', 'AED NL'));
        $this->bases->saveForGroup('Reanibex 100 Semi-Auto', new GroupBase('50001', 'FR', 'AED FR'));
        $this->accessoires->save(new Accessoire('60110', 'Rugzak'));
        $this->accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $this->links->link('Reanibex 100 Semi-Auto', '60110');
        $this->links->link('Reanibex 100 Semi-Auto', '60112');

        $this->variants->regenerateForGroup('Reanibex 100 Semi-Auto');

        $result = $this->variants->findAllForGroup('Reanibex 100 Semi-Auto');
        self::assertCount(6, $result, '2 bases × (geen + 2 accessoires) = 6 varianten');

        $signatures = [];
        foreach ($result as $variant) {
            $signatures[] = $variant->baseItemcode . '|' . ($variant->accessoireItemcode ?? '');
        }
        sort($signatures);
        self::assertSame(
            [
                '50001|',
                '50001|60110',
                '50001|60112',
                '50013|',
                '50013|60110',
                '50013|60112',
            ],
            $signatures,
        );
    }

    #[Test]
    public function regenerationIsIdempotent(): void
    {
        $this->bases->saveForGroup('Reanibex 100 Semi-Auto', new GroupBase('50013', 'NL', 'AED NL'));
        $this->accessoires->save(new Accessoire('60110', 'Rugzak'));
        $this->links->link('Reanibex 100 Semi-Auto', '60110');

        $this->variants->regenerateForGroup('Reanibex 100 Semi-Auto');
        $this->variants->regenerateForGroup('Reanibex 100 Semi-Auto');
        $this->variants->regenerateForGroup('Reanibex 100 Semi-Auto');

        self::assertCount(2, $this->variants->findAllForGroup('Reanibex 100 Semi-Auto'));
    }

    #[Test]
    public function rejectsRegenerateForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->variants->regenerateForGroup('Onbekend');
    }

    #[Test]
    public function rejectsFindForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->variants->findAllForGroup('Onbekend');
    }
}
