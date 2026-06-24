<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Afas;

use Defibrion\Samenstellingen\Application\Afas\PullAfasSamenstellingen;
use Defibrion\Samenstellingen\Application\Afas\PullAfasSamenstellingenHandler;
use Defibrion\Samenstellingen\Application\Group\SyncAllGroupsHandler;
use Defibrion\Samenstellingen\Application\Group\SyncGroupAgainstAfasHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticlesFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstenFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijzenFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\ItemProductTypes;
use Defibrion\Samenstellingen\Domain\Afas\VariantMatcher;
use Defibrion\Samenstellingen\Domain\Group\FamilyHeadShiftDetector;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use Defibrion\Samenstellingen\Infrastructure\Afas\InMemory\InMemoryAfasSamenstellingenFetcher;
use Defibrion\Samenstellingen\Infrastructure\Afas\InMemory\InMemoryPowerBiItemFetcher;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasArticleRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Publications\NullFreeFieldStateRefresher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PullAfasSamenstellingenHandlerTest extends TestCase
{
    #[Test]
    public function renamesGroupBasesWhenAfasNameChanged(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);

        $groups->save(new Group('Powerheart G5', '11148'));
        $persisted = $bases->saveForGroup(
            '11148',
            new GroupBase(null, 'AED pakket: Powerheart G5 semi-automaat NL', 'NL', '11148'),
        );
        self::assertNotNull($persisted->id);

        $samenstellingenFetcher = (new InMemoryAfasSamenstellingenFetcher())->withSamenstellingen(
            new AfasSamenstelling('11148', 'AED pakket: Powerheart G5 semi-automaat NL-EN', null, []),
        );

        $handler = $this->buildHandler($groups, $bases, $samenstellingenFetcher);
        $result = ($handler)(new PullAfasSamenstellingen());

        self::assertSame(1, $result->basesRenamed);
        $found = $bases->findById($persisted->id);
        self::assertNotNull($found);
        self::assertSame('AED pakket: Powerheart G5 semi-automaat NL-EN', $found->name);
    }

    #[Test]
    public function appliesProductTypesFromPowerBiOntoSnapshot(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $samenstellingenRepo = new InMemoryAfasSamenstellingenRepository();

        $samenstellingenFetcher = (new InMemoryAfasSamenstellingenFetcher())->withSamenstellingen(
            new AfasSamenstelling('11111-60110', 'AED Pakket met Rugtas', '11111', []),
            new AfasSamenstelling('11111', 'AED Pakket', '11111', []),
        );
        $productTypeFetcher = (new InMemoryPowerBiItemFetcher())->withProductTypes(
            new ItemProductTypes('11111-60110', 'AED pakket', '350P'),
            new ItemProductTypes('99999-onbekend', 'X', 'Y'),
        );

        $handler = $this->buildHandler($groups, $bases, $samenstellingenFetcher, $samenstellingenRepo, $productTypeFetcher);
        ($handler)(new PullAfasSamenstellingen());

        $variant = $samenstellingenRepo->findByItemcode('11111-60110');
        self::assertNotNull($variant);
        self::assertSame('AED pakket', $variant->productType01);
        self::assertSame('350P', $variant->productType02);

        $base = $samenstellingenRepo->findByItemcode('11111');
        self::assertNotNull($base);
        self::assertNull($base->productType01);
    }

    #[Test]
    public function leavesBaseUntouchedWhenAfasNameUnchanged(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);

        $groups->save(new Group('Powerheart G5', '11148'));
        $persisted = $bases->saveForGroup(
            '11148',
            new GroupBase(null, 'AED pakket NL', 'NL', '11148'),
        );
        self::assertNotNull($persisted->id);

        $samenstellingenFetcher = (new InMemoryAfasSamenstellingenFetcher())->withSamenstellingen(
            new AfasSamenstelling('11148', 'AED pakket NL', null, []),
        );

        $handler = $this->buildHandler($groups, $bases, $samenstellingenFetcher);
        $result = ($handler)(new PullAfasSamenstellingen());

        self::assertSame(0, $result->basesRenamed);
    }

    private function buildHandler(
        InMemoryGroupRepository $groups,
        InMemoryGroupBaseRepository $bases,
        InMemoryAfasSamenstellingenFetcher $samenstellingenFetcher,
        ?InMemoryAfasSamenstellingenRepository $samenstellingenRepo = null,
        ?InMemoryPowerBiItemFetcher $productTypeFetcher = null,
    ): PullAfasSamenstellingenHandler {
        $samenstellingenRepo ??= new InMemoryAfasSamenstellingenRepository();
        $productTypeFetcher ??= new InMemoryPowerBiItemFetcher();

        $variantRepo = new class () implements GroupVariantRepository {
            public function regenerateForGroup(string $familyHeadItemcode): void
            {
            }
            /** @return list<GroupVariant> */
            public function findAllForGroup(string $familyHeadItemcode): array
            {
                return [];
            }
            public function markMatched(int $variantId, string $afasItemcode): void
            {
            }
            public function markNoMatch(int $variantId): void
            {
            }
            /** @return list<string> */
            public function findMatchedAfasItemcodesForBase(int $baseId): array
            {
                return [];
            }
        };
        $baseItems = new class () implements GroupBaseItemRepository {
            public function saveForBase(int $baseId, GroupBaseItem $item): void
            {
            }
            /** @return list<GroupBaseItem> */
            public function findAllForBase(int $baseId): array
            {
                return [];
            }
            public function deleteByItemcode(string $itemcode): int
            {
                return 0;
            }
        };

        $syncGroup = new SyncGroupAgainstAfasHandler(
            $variantRepo,
            $baseItems,
            $samenstellingenRepo,
            new VariantMatcher(),
        );
        $syncAll = new SyncAllGroupsHandler($groups, $syncGroup, $samenstellingenRepo);

        return new PullAfasSamenstellingenHandler(
            $samenstellingenFetcher,
            $samenstellingenRepo,
            $productTypeFetcher,
            new class () implements AfasArticlesFetcher {
                /** @return list<AfasArticle> */
                public function fetchAll(): array
                {
                    return [];
                }
            },
            new InMemoryAfasArticleRepository(),
            $syncAll,
            new class () implements AfasPrijzenFetcher {
                /** @return list<AfasPrijs> */
                public function fetchActive(): array
                {
                    return [];
                }
            },
            new InMemoryAfasPrijsRepository(),
            new class () implements AfasPrijslijstenFetcher {
                /** @return list<AfasPrijslijst> */
                public function fetchAll(): array
                {
                    return [];
                }
            },
            new InMemoryAfasPrijslijstRepository(),
            $groups,
            $bases,
            new FamilyHeadShiftDetector(),
            new NullFreeFieldStateRefresher(),
        );
    }
}
