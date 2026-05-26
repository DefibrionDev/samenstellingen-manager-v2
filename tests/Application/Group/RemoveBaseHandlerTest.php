<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\RemoveBase;
use Defibrion\Samenstellingen\Application\Group\RemoveBaseHandler;
use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveBaseHandlerTest extends TestCase
{
    #[Test]
    public function removesBaseAndRegeneratesVariantsForGroup(): void
    {
        [$groups, $bases, $variants, $handler] = $this->wiring();
        $groups->save(new Group('Reanibex', '52112'));
        $persisted = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($persisted->id);
        $variants->regenerateForGroup('52112');
        self::assertCount(1, $variants->findAllForGroup('52112'));

        $result = $handler(new RemoveBase($persisted->id));

        self::assertSame($persisted->id, $result->baseId);
        self::assertSame('AED pakket NL', $result->baseName);
        self::assertSame('52112', $result->familyHeadItemcode);
        self::assertNull($bases->findById($persisted->id));
        // Variants zijn meegeregenereerd; groep heeft nu geen bases meer.
        self::assertSame([], $variants->findAllForGroup('52112'));
    }

    #[Test]
    public function throwsForUnknownBaseId(): void
    {
        [, , , $handler] = $this->wiring();

        $this->expectException(BaseNotFoundException::class);
        $handler(new RemoveBase(9999));
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryGroupBaseRepository, 2: InMemoryGroupVariantRepository, 3: RemoveBaseHandler}
     */
    private function wiring(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $handler = new RemoveBaseHandler($bases, $variants);

        return [$groups, $bases, $variants, $handler];
    }
}
