<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\ShowGroup;
use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShowGroupHandlerTest extends TestCase
{
    #[Test]
    public function returnsOverviewWithBomsPerVariant(): void
    {
        [$groups, $bases, $baseItems, $accessoires, $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $persistedBase = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL'));
        self::assertNotNull($persistedBase->id);
        $baseItems->saveForBase($persistedBase->id, new GroupBaseItem('50013', 'AED Nederlands'));
        $baseItems->saveForBase($persistedBase->id, new GroupBaseItem('50015', 'Electrode'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links->link('52112', '60112');
        $variants->regenerateForGroup('52112');

        $handler = new ShowGroupHandler($groups, $bases, $baseItems, $links, $variants);
        $overview = $handler(new ShowGroup('52112'));

        self::assertSame('Reanibex 100 Semi-Auto', $overview->group->name);
        self::assertCount(1, $overview->bases);
        self::assertCount(1, $overview->accessoires);
        self::assertCount(2, $overview->variants, '1 base × (geen + 1 accessoire) = 2 varianten');

        // Base-only variant: 2 BOM items (alleen base items).
        self::assertCount(2, $overview->variants[0]->bom);
        // Variant met accessoire: 2 base items + 1 accessoire = 3 BOM items.
        self::assertCount(3, $overview->variants[1]->bom);
        self::assertSame('60112', $overview->variants[1]->bom[2]->itemcode);
    }

    #[Test]
    public function throwsWhenGroupNotFound(): void
    {
        [$groups, $bases, $baseItems, , $links, $variants] = $this->repos();
        $handler = new ShowGroupHandler($groups, $bases, $baseItems, $links, $variants);

        $this->expectException(GroupNotFoundException::class);

        $handler(new ShowGroup('99999'));
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryGroupBaseRepository, 2: InMemoryGroupBaseItemRepository, 3: InMemoryAccessoireRepository, 4: InMemoryGroupAccessoireRepository, 5: InMemoryGroupVariantRepository}
     */
    private function repos(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $baseItems = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        return [$groups, $bases, $baseItems, $accessoires, $links, $variants];
    }
}
