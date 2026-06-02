<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Bom;

use Defibrion\Samenstellingen\Application\Bom\StripBomComponent;
use Defibrion\Samenstellingen\Application\Bom\StripBomComponentHandler;
use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Bom\InMemory\InMemoryBomComponentStripWriter;
use Defibrion\Samenstellingen\Infrastructure\Bom\InMemory\InMemoryBomLineReader;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StripBomComponentHandlerTest extends TestCase
{
    #[Test]
    public function dryRunListsLinesWithoutMutating(): void
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11142-EN', '81611', 'Sam', 30),
            new BomLine('11145-EN', '81611', 'Sam', 30),
        );
        $writer = new InMemoryBomComponentStripWriter();
        $items = $this->seedToolItem('11142-EN', '81611');

        $handler = new StripBomComponentHandler($reader, $writer, $items);
        $result = ($handler)(new StripBomComponent('81611'));

        self::assertCount(2, $result->plannedLines);
        self::assertSame(0, $result->toolRowsDeleted);
        self::assertSame(0, $result->appliedCount);
        self::assertSame([], $writer->applied);
    }

    #[Test]
    public function applyDeletesToolRowsAndAfasLines(): void
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11142-EN', '81611', 'Sam', 30),
            new BomLine('11145-EN', '81611', 'Sam', 20),
        );
        $writer = new InMemoryBomComponentStripWriter();
        $items = $this->seedToolItem('11142-EN', '81611');

        $handler = new StripBomComponentHandler($reader, $writer, $items);
        $result = ($handler)(new StripBomComponent('81611', apply: true));

        self::assertSame(1, $result->toolRowsDeleted);
        self::assertSame(2, $result->appliedCount);
        self::assertCount(2, $writer->applied);
        self::assertSame([], $result->failures);
    }

    #[Test]
    public function applyCollectsFailuresPerLine(): void
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11142-EN', '81611', 'Sam', 30),
            new BomLine('11145-EN', '81611', 'Sam', 20),
        );
        $writer = (new InMemoryBomComponentStripWriter())->failOn('11145-EN', '81611');
        $items = $this->seedToolItem('11142-EN', '81611');

        $result = (new StripBomComponentHandler($reader, $writer, $items))(
            new StripBomComponent('81611', apply: true),
        );

        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $result->failures);
        self::assertSame('11145-EN', $result->failures[0]['line']->samenstellingItemcode);
    }

    #[Test]
    public function limitTruncatesPlanBeforeApply(): void
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11142-EN', '81611', 'Sam', 30),
            new BomLine('11145-EN', '81611', 'Sam', 20),
            new BomLine('11148-EN', '81611', 'Sam', 30),
        );
        $writer = new InMemoryBomComponentStripWriter();
        $items = $this->seedToolItem('11142-EN', '81611');

        $result = (new StripBomComponentHandler($reader, $writer, $items))(
            new StripBomComponent('81611', apply: true, limit: 2),
        );

        self::assertCount(2, $result->plannedLines);
        self::assertSame(2, $result->appliedCount);
    }

    private function seedToolItem(string $baseLabel, string $itemcode): InMemoryGroupBaseItemRepository
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);

        $groups->save(new Group($baseLabel, '11142'));
        $persisted = $bases->saveForGroup('11142', new GroupBase(null, 'AED pakket EN', 'EN', $baseLabel));
        self::assertNotNull($persisted->id);
        $items->saveForBase($persisted->id, new GroupBaseItem($itemcode, 'sticker'));

        return $items;
    }
}
