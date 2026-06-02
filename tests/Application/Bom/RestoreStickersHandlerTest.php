<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Bom;

use Defibrion\Samenstellingen\Application\Bom\RestoreStickers;
use Defibrion\Samenstellingen\Application\Bom\RestoreStickersHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Bom\InMemory\InMemoryBomComponentRestoreWriter;
use Defibrion\Samenstellingen\Infrastructure\Bom\InMemory\InMemoryBomLineReader;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RestoreStickersHandlerTest extends TestCase
{
    #[Test]
    public function dryRunPlansToolInsertAndAfasInsertForEnBaseWithoutSticker(): void
    {
        $h = $this->buildHandler(
            languageCode: 'EN',
            baseAfasItemcode: '11142-EN',
            existingToolItems: ['10142-EN', '70112'],
            afasSamenstellingen: [
                new AfasSamenstelling('11142-EN', 'AED EN', null, ['10142-EN', '70112']),
                new AfasSamenstelling('11142-EN-60110', 'AED EN tas', '11142-EN', ['10142-EN', '70112', '60110']),
            ],
            currentBomLines: [],
        );

        $result = ($h['handler'])(new RestoreStickers());

        self::assertCount(1, $result->toolInserts);
        self::assertSame('81611', $result->toolInserts[0]['sticker']);
        self::assertCount(2, $result->afasPlans);
        self::assertSame('11142-EN', $result->afasPlans[0]->samenstellingItemcode);
        self::assertSame('81611', $result->afasPlans[0]->bomItemcode);
        self::assertSame('Sam', $result->afasPlans[0]->vaIt);
        self::assertSame(0, $result->toolInsertedCount);
    }

    #[Test]
    public function applyInsertsToolItemAndCallsAfasWriter(): void
    {
        $h = $this->buildHandler(
            languageCode: 'EN',
            baseAfasItemcode: '11142-EN',
            existingToolItems: ['10142-EN'],
            afasSamenstellingen: [
                new AfasSamenstelling('11142-EN', 'AED EN', null, ['10142-EN']),
            ],
            currentBomLines: [
                new BomLine('11142-EN', '10142-EN', 'Art', 10),
            ],
        );

        $result = ($h['handler'])(new RestoreStickers(apply: true));

        self::assertSame(1, $result->toolInsertedCount);
        self::assertSame(1, $result->afasAppliedCount);
        self::assertCount(1, $h['writer']->applied);
        self::assertSame(20, $h['writer']->applied[0]->prSe);
    }

    #[Test]
    public function nlBaseWithStickerPresentIsNoOp(): void
    {
        $h = $this->buildHandler(
            languageCode: 'NL',
            baseAfasItemcode: '11142',
            existingToolItems: ['81111', '10142', '70112'],
            afasSamenstellingen: [
                new AfasSamenstelling('11142', 'AED NL', null, ['10142', '70112', '81111']),
            ],
            currentBomLines: [],
        );

        $result = ($h['handler'])(new RestoreStickers());

        self::assertSame([], $result->toolInserts);
        self::assertSame([], $result->afasPlans);
    }

    #[Test]
    public function languageFilterSkipsOtherLanguages(): void
    {
        $h = $this->buildHandler(
            languageCode: 'NL',
            baseAfasItemcode: '11142',
            existingToolItems: ['10142', '70112'],
            afasSamenstellingen: [
                new AfasSamenstelling('11142', 'AED NL', null, ['10142', '70112']),
            ],
            currentBomLines: [],
        );

        $result = ($h['handler'])(new RestoreStickers(languageCode: 'EN'));

        self::assertSame([], $result->toolInserts);
        self::assertSame([], $result->afasPlans);
    }

    /**
     * @param list<string>                                            $existingToolItems
     * @param list<AfasSamenstelling>                                 $afasSamenstellingen
     * @param list<BomLine>                                           $currentBomLines
     *
     * @return array{handler: RestoreStickersHandler, writer: InMemoryBomComponentRestoreWriter}
     */
    private function buildHandler(
        string $languageCode,
        string $baseAfasItemcode,
        array $existingToolItems,
        array $afasSamenstellingen,
        array $currentBomLines,
    ): array {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot($afasSamenstellingen);

        $familyHead = $baseAfasItemcode;
        $groups->save(new Group('AED Test', $familyHead));
        $persisted = $bases->saveForGroup($familyHead, new GroupBase(null, 'AED pakket', $languageCode, $baseAfasItemcode));
        self::assertNotNull($persisted->id);
        foreach ($existingToolItems as $code) {
            $items->saveForBase($persisted->id, new GroupBaseItem($code, 'item'));
        }

        $reader = (new InMemoryBomLineReader())->withLines(...$currentBomLines);
        $writer = new InMemoryBomComponentRestoreWriter();
        $handler = new RestoreStickersHandler($groups, $bases, $items, $afas, $reader, $writer);

        return ['handler' => $handler, 'writer' => $writer];
    }
}
