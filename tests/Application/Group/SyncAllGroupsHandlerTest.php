<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\SyncAllGroups;
use Defibrion\Samenstellingen\Application\Group\SyncAllGroupsHandler;
use Defibrion\Samenstellingen\Application\Group\SyncGroupAgainstAfasHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\VariantMatcher;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyncAllGroupsHandlerTest extends TestCase
{
    #[Test]
    public function reportsZeroAndSkipReasonWhenSnapshotEmpty(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Reanibex', '52112'));

        $summary = ($this->handler($bag))(new SyncAllGroups());

        self::assertSame(0, $summary->groupsProcessed);
        self::assertSame(1, $summary->groupsSkipped);
        self::assertNotEmpty($summary->skipReasons);
    }

    #[Test]
    public function returnsEmptySummaryWhenNoGroupsExistAndSnapshotEmpty(): void
    {
        $summary = ($this->handler($this->bag()))(new SyncAllGroups());

        self::assertSame(0, $summary->groupsProcessed);
        self::assertSame(0, $summary->groupsSkipped);
        self::assertSame([], $summary->skipReasons);
    }

    #[Test]
    public function aggregatesMatchedAndNoMatchAcrossGroups(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Reanibex', '52112'));
        $base = $bag['bases']->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        self::assertNotNull($base->id);
        $bag['items']->saveForBase($base->id, new GroupBaseItem('50013', 'AED NL'));
        $bag['variants']->regenerateForGroup('52112');

        // AFAS-snapshot: één matching samenstelling.
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('52112', 'AED pakket NL', null, ['50013']),
        ]);

        $summary = ($this->handler($bag))(new SyncAllGroups());

        self::assertSame(1, $summary->groupsProcessed);
        self::assertSame(1, $summary->matched);
        self::assertSame(0, $summary->noMatch);
    }

    /**
     * @return array{
     *     groups: InMemoryGroupRepository,
     *     bases: InMemoryGroupBaseRepository,
     *     items: InMemoryGroupBaseItemRepository,
     *     accessoires: InMemoryAccessoireRepository,
     *     links: InMemoryGroupAccessoireRepository,
     *     variants: InMemoryGroupVariantRepository,
     *     afas: InMemoryAfasSamenstellingenRepository
     * }
     */
    private function bag(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        return compact('groups', 'bases', 'items', 'accessoires', 'links', 'variants', 'afas');
    }

    /**
     * @param array<string, mixed> $bag
     */
    private function handler(array $bag): SyncAllGroupsHandler
    {
        $sync = new SyncGroupAgainstAfasHandler(
            $bag['variants'],
            $bag['items'],
            $bag['afas'],
            new VariantMatcher(),
        );

        return new SyncAllGroupsHandler($bag['groups'], $sync, $bag['afas']);
    }
}
