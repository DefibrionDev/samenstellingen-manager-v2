<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Group;

use Defibrion\Samenstellingen\Domain\Group\BaseItemAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class GroupBaseItemRepositoryContractTestCase extends TestCase
{
    /**
     * @return array{groups: GroupRepository, bases: GroupBaseRepository, items: GroupBaseItemRepository}
     */
    abstract protected function makeRepositories(): array;

    private GroupRepository $groups;
    private GroupBaseRepository $bases;
    private GroupBaseItemRepository $items;
    private int $baseId;

    protected function setUp(): void
    {
        $repos = $this->makeRepositories();
        $this->groups = $repos['groups'];
        $this->bases = $repos['bases'];
        $this->items = $repos['items'];

        $this->groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $persisted = $this->bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL'));
        self::assertNotNull($persisted->id);
        $this->baseId = $persisted->id;
    }

    #[Test]
    public function savesAndRetrievesItem(): void
    {
        $this->items->saveForBase($this->baseId, new GroupBaseItem('50013', 'AED NL'));
        $this->items->saveForBase($this->baseId, new GroupBaseItem('50015', 'Electrode'));

        $items = $this->items->findAllForBase($this->baseId);

        self::assertCount(2, $items);
        $itemcodes = array_map(static fn (GroupBaseItem $i) => $i->itemcode, $items);
        self::assertContains('50013', $itemcodes);
        self::assertContains('50015', $itemcodes);
    }

    #[Test]
    public function returnsEmptyListForBaseWithoutItems(): void
    {
        self::assertSame([], $this->items->findAllForBase($this->baseId));
    }

    #[Test]
    public function rejectsDuplicateItemInSameBase(): void
    {
        $this->items->saveForBase($this->baseId, new GroupBaseItem('50013', 'AED NL'));

        $this->expectException(BaseItemAlreadyExistsException::class);

        $this->items->saveForBase($this->baseId, new GroupBaseItem('50013', 'iets anders'));
    }

    #[Test]
    public function rejectsSaveForUnknownBase(): void
    {
        $this->expectException(BaseNotFoundException::class);

        $this->items->saveForBase(9999, new GroupBaseItem('50013', 'AED NL'));
    }

    #[Test]
    public function rejectsFindForUnknownBase(): void
    {
        $this->expectException(BaseNotFoundException::class);

        $this->items->findAllForBase(9999);
    }
}
