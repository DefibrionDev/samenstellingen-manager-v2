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

    #[Test]
    public function onlyWithKeepsLinesOfSamenstellingenThatAlsoContainOneOfTheGivenComponents(): void
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11145-DE', '81111', 'Sam', 30),
            new BomLine('11145-DE', '81511', 'Sam', 40),
            new BomLine('11142', '81111', 'Sam', 30),
            new BomLine('11145-EN', '81111', 'Sam', 30),
            new BomLine('11145-EN', '81611', 'Sam', 40),
        );
        $writer = new InMemoryBomComponentStripWriter();
        $items = $this->seedToolItem('11142', '81111');

        $handler = new StripBomComponentHandler($reader, $writer, $items);
        $result = ($handler)(new StripBomComponent('81111', onlyWith: ['81511', '81611']));

        self::assertSame(
            ['11145-DE', '11145-EN'],
            array_map(static fn (BomLine $l): string => $l->samenstellingItemcode, $result->plannedLines),
        );
        self::assertSame(1, $result->skippedCount);
        self::assertSame([], $writer->applied);
    }

    #[Test]
    public function applyWithOnlyWithDeletesToolRowsOnlyForSamenstellingenInScope(): void
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11145-DE', '81111', 'Sam', 30),
            new BomLine('11145-DE', '81511', 'Sam', 40),
            new BomLine('11142', '81111', 'Sam', 30),
        );
        $writer = new InMemoryBomComponentStripWriter();
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $groups->save(new Group('HS1', '11145-EN'));
        $de = $bases->saveForGroup('11145-EN', new GroupBase(null, 'AED pakket DE', 'DE', '11145-DE'));
        $groups->save(new Group('HS1 vol', '11142'));
        $nl = $bases->saveForGroup('11142', new GroupBase(null, 'AED pakket NL', 'NL', '11142'));
        self::assertNotNull($de->id);
        self::assertNotNull($nl->id);
        $items->saveForBase($de->id, new GroupBaseItem('81111', 'sticker NL'));
        $items->saveForBase($nl->id, new GroupBaseItem('81111', 'sticker NL'));

        $handler = new StripBomComponentHandler($reader, $writer, $items);
        $result = ($handler)(new StripBomComponent('81111', apply: true, onlyWith: ['81511']));

        self::assertSame(1, $result->toolRowsDeleted);
        self::assertSame(1, $result->appliedCount);
        self::assertSame(1, $result->skippedCount);
        self::assertSame([], $items->findAllForBase($de->id));
        self::assertCount(1, $items->findAllForBase($nl->id));
    }

    #[Test]
    public function linesWhosePrSeIsNotUniqueInTheirSamenstellingAreSkippedAsUnsafe(): void
    {
        // AFAS matcht een FbCompositionPart-delete op PrSe alléén (incident 11167,
        // 10 sep 2026): bij een gedeelde PrSe kan de verkeerde regel verdwijnen.
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11167', '81111', 'Sam', 10),
            new BomLine('11167', '10165', 'Art', 10),   // deelt PrSe 10
            new BomLine('11167', '81211', 'Sam', 20),
            new BomLine('11145-DE', '81111', 'Sam', 30),
            new BomLine('11145-DE', '10145-DE', 'Art', 10),
            new BomLine('11145-DE', '81511', 'Sam', 40),
        );
        $writer = new InMemoryBomComponentStripWriter();
        $items = $this->seedToolItem('11167', '81111');

        $handler = new StripBomComponentHandler($reader, $writer, $items);
        $result = ($handler)(new StripBomComponent('81111', apply: true, onlyWith: ['81211', '81511']));

        self::assertSame(['11145-DE'], array_map(static fn (BomLine $l): string => $l->samenstellingItemcode, $result->plannedLines));
        self::assertSame(['11167'], array_map(static fn (BomLine $l): string => $l->samenstellingItemcode, $result->unsafeLines));
        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $writer->applied);
        self::assertSame(0, $result->toolRowsDeleted, 'registratie van 11167 blijft staan zolang AFAS 81111 nog heeft');
    }

    #[Test]
    public function withoutOnlyWithNothingIsSkipped(): void
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11145-DE', '81111', 'Sam', 30),
            new BomLine('11142', '81111', 'Sam', 30),
        );
        $handler = new StripBomComponentHandler(
            $reader,
            new InMemoryBomComponentStripWriter(),
            $this->seedToolItem('11142', '81111'),
        );

        $result = ($handler)(new StripBomComponent('81111'));

        self::assertCount(2, $result->plannedLines);
        self::assertSame(0, $result->skippedCount);
    }

    #[Test]
    public function onlyWithCodesAreTrimmedAndEmptyOnesDropped(): void
    {
        $command = new StripBomComponent('81111', onlyWith: [' 81511 ', '', '81611']);

        self::assertSame(['81511', '81611'], $command->onlyWith);
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
