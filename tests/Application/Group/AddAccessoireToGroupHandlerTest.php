<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroup;
use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
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

final class AddAccessoireToGroupHandlerTest extends TestCase
{
    #[Test]
    public function linksAccessoireAndRegeneratesVariants(): void
    {
        [$groups, $bases, $accessoires, $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $handler = new AddAccessoireToGroupHandler($links, $variants);

        $handler(new AddAccessoireToGroup('52112', '60112'));

        self::assertCount(1, $links->findAllForGroup('52112'));
        // 1 base × (geen + 1 accessoire) = 2 varianten.
        self::assertCount(2, $variants->findAllForGroup('52112'));
    }

    #[Test]
    public function passesThroughGroupNotFound(): void
    {
        [, , , $links, $variants] = $this->repos();
        $handler = new AddAccessoireToGroupHandler($links, $variants);

        $this->expectException(GroupNotFoundException::class);

        $handler(new AddAccessoireToGroup('99999', '60112'));
    }

    #[Test]
    public function passesThroughAccessoireNotFound(): void
    {
        [$groups, , , $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $handler = new AddAccessoireToGroupHandler($links, $variants);

        $this->expectException(AccessoireNotFoundException::class);

        $handler(new AddAccessoireToGroup('52112', '99999'));
    }

    #[Test]
    public function passesThroughDuplicateLink(): void
    {
        [$groups, , $accessoires, $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $handler = new AddAccessoireToGroupHandler($links, $variants);
        $handler(new AddAccessoireToGroup('52112', '60112'));

        $this->expectException(AccessoireAlreadyLinkedException::class);

        $handler(new AddAccessoireToGroup('52112', '60112'));
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryGroupBaseRepository, 2: InMemoryAccessoireRepository, 3: InMemoryGroupAccessoireRepository, 4: InMemoryGroupVariantRepository}
     */
    private function repos(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        return [$groups, $bases, $accessoires, $links, $variants];
    }
}
