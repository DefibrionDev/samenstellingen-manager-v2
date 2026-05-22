<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\SyncGroupAgainstAfas;
use Defibrion\Samenstellingen\Application\Group\SyncGroupAgainstAfasHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
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
use RuntimeException;

final class SyncGroupAgainstAfasHandlerTest extends TestCase
{
    #[Test]
    public function matchesVariantsAgainstLocalAfasSnapshot(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $baseItems = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afasRepo = new InMemoryAfasSamenstellingenRepository();

        $base = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL'));
        self::assertNotNull($base->id);
        $baseItems->saveForBase($base->id, new GroupBaseItem('50013', 'AED NL'));
        $baseItems->saveForBase($base->id, new GroupBaseItem('50015', 'Electrode'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links->link('52112', '60112');
        $variants->regenerateForGroup('52112');

        $afasRepo->replaceSnapshot([
            new AfasSamenstelling('52112', 'AED pakket NL', '52112', ['50013', '50015']),
            // Geen samenstelling met BOM = [50013, 50015, 60112], dus variant met accessoire vindt geen match.
        ]);

        $handler = new SyncGroupAgainstAfasHandler($variants, $baseItems, $afasRepo, new VariantMatcher());
        $summary = $handler(new SyncGroupAgainstAfas('52112'));

        self::assertSame(1, $summary->matchCount());
        self::assertSame(1, $summary->noMatchCount());
        self::assertSame('52112', $summary->matched[0]['afasItemcode']);

        $afterSync = $variants->findAllForGroup('52112');
        self::assertSame('matched', $afterSync[0]->afasStatus);
        self::assertSame('52112', $afterSync[0]->afasSamenstellingItemcode);
        self::assertSame('no_match', $afterSync[1]->afasStatus);
        self::assertNull($afterSync[1]->afasSamenstellingItemcode);
    }

    #[Test]
    public function throwsWhenSnapshotIsEmpty(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $baseItems = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afasRepo = new InMemoryAfasSamenstellingenRepository();

        $handler = new SyncGroupAgainstAfasHandler($variants, $baseItems, $afasRepo, new VariantMatcher());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lokale AFAS-snapshot is leeg');

        $handler(new SyncGroupAgainstAfas('52112'));
    }
}
