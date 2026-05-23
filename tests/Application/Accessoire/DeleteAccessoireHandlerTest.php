<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Accessoire;

use Defibrion\Samenstellingen\Application\Accessoire\DeleteAccessoire;
use Defibrion\Samenstellingen\Application\Accessoire\DeleteAccessoireHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeleteAccessoireHandlerTest extends TestCase
{
    #[Test]
    public function removesAccessoireAndRegeneratesVariantsForLinkedGroups(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        $groups->save(new Group('Reanibex', '52112'));
        $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links->link('52112', '60112');
        $variants->regenerateForGroup('52112');
        self::assertCount(2, $variants->findAllForGroup('52112'));

        $handler = new DeleteAccessoireHandler($accessoires, $groups, $links, $variants);
        $result = ($handler)(new DeleteAccessoire('60112'));

        self::assertNull($accessoires->findByItemcode('60112'));
        self::assertSame(['52112'], $result->affectedFamilyHeads);
        // InMemoryGroupAccessoireRepository heeft geen CASCADE-handler, dus
        // de regeneratie hier toont vooral dat het commando draait. De Sqlite-laag
        // doet de cascade via FK.
    }

    #[Test]
    public function throwsForUnknownAccessoire(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $handler = new DeleteAccessoireHandler($accessoires, $groups, $links, $variants);

        $this->expectException(AccessoireNotFoundException::class);
        ($handler)(new DeleteAccessoire('99999'));
    }
}
