<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\ShowGroup;
use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShowGroupHandlerTest extends TestCase
{
    #[Test]
    public function returnsOverviewWithVariants(): void
    {
        [$groups, $bases, $accessoires, $links, $variants] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases->saveForGroup('Reanibex 100 Semi-Auto', new GroupBase('50013', 'NL', 'AED NL'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links->link('Reanibex 100 Semi-Auto', '60112');
        $variants->regenerateForGroup('Reanibex 100 Semi-Auto');

        $handler = new ShowGroupHandler($groups, $bases, $links, $variants);
        $overview = $handler(new ShowGroup('Reanibex 100 Semi-Auto'));

        self::assertSame('Reanibex 100 Semi-Auto', $overview->group->name);
        self::assertCount(1, $overview->bases);
        self::assertCount(1, $overview->accessoires);
        self::assertCount(2, $overview->variants, '1 base × (geen + 1 accessoire) = 2 varianten');
    }

    #[Test]
    public function returnsEmptyVariantsListWhenNothingGeneratedYet(): void
    {
        [$groups, $bases, , $links, $variants] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));

        $handler = new ShowGroupHandler($groups, $bases, $links, $variants);
        $overview = $handler(new ShowGroup('Reanibex 100 Semi-Auto'));

        self::assertSame([], $overview->bases);
        self::assertSame([], $overview->accessoires);
        self::assertSame([], $overview->variants);
    }

    #[Test]
    public function throwsWhenGroupNotFound(): void
    {
        [$groups, $bases, , $links, $variants] = $this->repositories();
        $handler = new ShowGroupHandler($groups, $bases, $links, $variants);

        $this->expectException(GroupNotFoundException::class);
        $this->expectExceptionMessage("Groep 'Onbekend' niet gevonden");

        $handler(new ShowGroup('Onbekend'));
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryGroupBaseRepository, 2: InMemoryAccessoireRepository, 3: InMemoryGroupAccessoireRepository, 4: InMemoryGroupVariantRepository}
     */
    private function repositories(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        return [$groups, $bases, $accessoires, $links, $variants];
    }
}
