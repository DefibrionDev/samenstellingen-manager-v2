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
    public function savesAndAssignsId(): void
    {
        $persisted = $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));

        self::assertNotNull($persisted->id);
        self::assertGreaterThan(0, $persisted->id);
        self::assertSame('AED pakket NL', $persisted->name);
    }

    #[Test]
    public function findsByIdAndForGroup(): void
    {
        $persisted = $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));

        self::assertNotNull($persisted->id);
        $byId = $this->bases->findById($persisted->id);
        self::assertNotNull($byId);
        self::assertSame('AED pakket NL', $byId->name);

        $forGroup = $this->bases->findAllForGroup('52112');
        self::assertCount(1, $forGroup);
        self::assertSame($persisted->id, $forGroup[0]->id);
    }

    #[Test]
    public function rejectsDuplicateNameInSameGroup(): void
    {
        $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));

        $this->expectException(BaseAlreadyExistsException::class);
        $this->expectExceptionMessage("Base met naam 'AED pakket NL' bestaat al in groep 52112");

        $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
    }

    #[Test]
    public function rejectsSaveForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->bases->saveForGroup('99999', new GroupBase(null, 'naam', 'NL'));
    }

    #[Test]
    public function rejectsFindAllForUnknownGroup(): void
    {
        $this->expectException(GroupNotFoundException::class);

        $this->bases->findAllForGroup('99999');
    }

    #[Test]
    public function returnsNullForUnknownBaseId(): void
    {
        self::assertNull($this->bases->findById(9999));
    }

    #[Test]
    public function deleteRemovesBase(): void
    {
        $persisted = $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        self::assertNotNull($persisted->id);

        $this->bases->delete($persisted->id);

        self::assertNull($this->bases->findById($persisted->id));
        self::assertSame([], $this->bases->findAllForGroup('52112'));
    }

    #[Test]
    public function deleteIsIdempotentForUnknownId(): void
    {
        $this->bases->delete(9999);
        self::assertNull($this->bases->findById(9999));
    }

    #[Test]
    public function findAllAfasItemcodesReturnsOnlyNonNullValues(): void
    {
        $this->bases->saveForGroup('52112', new GroupBase(null, 'AED NL', 'NL', '52112'));
        $this->bases->saveForGroup('52112', new GroupBase(null, 'Pack DAE FR', 'FR', '52124'));
        $this->bases->saveForGroup('52112', new GroupBase(null, 'AED zonder SKU', 'DE', null));

        $codes = $this->bases->findAllAfasItemcodes();
        sort($codes);

        self::assertSame(['52112', '52124'], $codes);
    }
}
