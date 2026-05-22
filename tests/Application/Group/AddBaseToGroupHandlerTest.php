<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\AddBaseToGroup;
use Defibrion\Samenstellingen\Application\Group\AddBaseToGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddBaseToGroupHandlerTest extends TestCase
{
    #[Test]
    public function persistsBaseAndRegeneratesVariants(): void
    {
        [$groups, $bases, , , $variants] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $handler = new AddBaseToGroupHandler($bases, $variants);

        $handler(new AddBaseToGroup(
            'Reanibex 100 Semi-Auto',
            '50013',
            'NL',
            'Reanibex 100 Semi-Automatic AED Nederlands',
        ));

        self::assertCount(1, $bases->findAllForGroup('Reanibex 100 Semi-Auto'));
        self::assertCount(1, $variants->findAllForGroup('Reanibex 100 Semi-Auto'));
    }

    #[Test]
    public function passesThroughGroupNotFound(): void
    {
        [, $bases, , , $variants] = $this->repositories();
        $handler = new AddBaseToGroupHandler($bases, $variants);

        $this->expectException(GroupNotFoundException::class);

        $handler(new AddBaseToGroup('Onbekend', '50013', 'NL', 'naam'));
    }

    #[Test]
    public function passesThroughDuplicateBase(): void
    {
        [$groups, $bases, , , $variants] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $handler = new AddBaseToGroupHandler($bases, $variants);
        $handler(new AddBaseToGroup('Reanibex 100 Semi-Auto', '50013', 'NL', 'naam'));

        $this->expectException(BaseAlreadyExistsException::class);

        $handler(new AddBaseToGroup('Reanibex 100 Semi-Auto', '50013', 'DE', 'andere'));
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
