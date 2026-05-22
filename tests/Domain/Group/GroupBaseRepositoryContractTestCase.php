<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Group;

use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class GroupBaseRepositoryContractTestCase extends TestCase
{
    /**
     * @return array{groups: GroupRepository, bases: GroupBaseRepository}
     */
    abstract protected function makeRepositories(): array;

    private GroupRepository $groups;
    private GroupBaseRepository $bases;

    protected function setUp(): void
    {
        $repos = $this->makeRepositories();
        $this->groups = $repos['groups'];
        $this->bases = $repos['bases'];

        $this->groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
    }

    #[Test]
    public function savesAndRetrievesBaseForGroup(): void
    {
        $base = new GroupBase('50013', 'NL', 'Reanibex 100 Semi-Automatic AED Nederlands');
        $this->bases->saveForGroup('Reanibex 100 Semi-Auto', $base);

        $found = $this->bases->findAllForGroup('Reanibex 100 Semi-Auto');

        self::assertCount(1, $found);
        self::assertSame('50013', $found[0]->itemcode);
        self::assertSame('NL', $found[0]->languageCode);
        self::assertSame('Reanibex 100 Semi-Automatic AED Nederlands', $found[0]->name);
    }

    #[Test]
    public function returnsEmptyListForGroupWithoutBases(): void
    {
        self::assertSame([], $this->bases->findAllForGroup('Reanibex 100 Semi-Auto'));
    }

    #[Test]
    public function rejectsDuplicateBaseInSameGroup(): void
    {
        $this->bases->saveForGroup(
            'Reanibex 100 Semi-Auto',
            new GroupBase('50013', 'NL', 'Reanibex 100 Semi-Automatic AED Nederlands'),
        );

        $this->expectException(BaseAlreadyExistsException::class);
        $this->expectExceptionMessage("Base met itemcode '50013' bestaat al in groep 'Reanibex 100 Semi-Auto'");

        $this->bases->saveForGroup(
            'Reanibex 100 Semi-Auto',
            new GroupBase('50013', 'DE', 'Reanibex 100 Semi-Automatic AED German'),
        );
    }

    #[Test]
    public function rejectsSaveForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);
        $this->expectExceptionMessage("Groep 'Onbekend' niet gevonden");

        $this->bases->saveForGroup('Onbekend', new GroupBase('50013', 'NL', 'naam'));
    }

    #[Test]
    public function rejectsFindForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->bases->findAllForGroup('Onbekend');
    }
}
