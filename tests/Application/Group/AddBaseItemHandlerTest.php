<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\AddBaseItem;
use Defibrion\Samenstellingen\Application\Group\AddBaseItemHandler;
use Defibrion\Samenstellingen\Domain\Group\BaseItemAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddBaseItemHandlerTest extends TestCase
{
    #[Test]
    public function addsItemToBase(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $persisted = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL'));
        $items = new InMemoryGroupBaseItemRepository($bases);
        $handler = new AddBaseItemHandler($items);

        self::assertNotNull($persisted->id);
        $item = $handler(new AddBaseItem($persisted->id, '50013', 'AED Nederlands'));

        self::assertSame('50013', $item->itemcode);
        self::assertCount(1, $items->findAllForBase($persisted->id));
    }

    #[Test]
    public function passesThroughBaseNotFound(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $handler = new AddBaseItemHandler($items);

        $this->expectException(BaseNotFoundException::class);

        $handler(new AddBaseItem(9999, '50013', 'AED'));
    }

    #[Test]
    public function passesThroughDuplicateItem(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $persisted = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL'));
        self::assertNotNull($persisted->id);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $handler = new AddBaseItemHandler($items);
        $handler(new AddBaseItem($persisted->id, '50013', 'AED'));

        $this->expectException(BaseItemAlreadyExistsException::class);

        $handler(new AddBaseItem($persisted->id, '50013', 'AED anders'));
    }
}
