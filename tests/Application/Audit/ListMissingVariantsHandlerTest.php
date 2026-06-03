<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariants;
use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
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

final class ListMissingVariantsHandlerTest extends TestCase
{
    #[Test]
    public function omitsNoMatchVariantsWhenSuggestedSkuExistsInAfasSnapshot(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        // Verwacht itemcode 11111-60110 staat in AFAS-snapshot → niet "missing".
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11111-60110', 'Variant met Backpack', '11111', []),
        ]);

        $rows = ($bag['handler'])(new ListMissingVariants());

        self::assertSame([], $rows);
    }

    #[Test]
    public function includesNoMatchVariantsWhenSuggestedSkuMissingFromAfasSnapshot(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        // AFAS bevat 11111-60110 NIET → moet als missing terugkomen.
        $bag['afas']->replaceSnapshot([]);

        $rows = ($bag['handler'])(new ListMissingVariants());

        self::assertCount(1, $rows);
        self::assertSame('11111-60110', $rows[0]->verwachteSkuVoorstel);
    }

    #[Test]
    public function omitsNoMatchVariantsWithEmptySuggestedSku(): void
    {
        // Base-only variant (geen accessoire) zonder base-AFAS-sku → verwachteSkuVoorstel = ''.
        // Die kan onmogelijk in AFAS bestaan, maar 'm includeren als "missing" is misleidend:
        // de tool weet niet welk itemcode 'ie zou moeten POSTen.
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        $groups->save(new Group('Heartsine 350P', '10013'));
        // Base zonder afas_itemcode → base-only-variant heeft afasSamenstellingItemcode null + accessoire null.
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'AED pakket NL', 'NL'));
        self::assertNotNull($base->id);
        $variants->regenerateForGroup('10013');
        foreach ($variants->findAllForGroup('10013') as $variant) {
            self::assertNotNull($variant->id);
            $variants->markNoMatch($variant->id);
        }

        $handler = new ListMissingVariantsHandler($groups, $variants, $items, $afas);

        $rows = $handler(new ListMissingVariants());

        self::assertSame([], $rows);
    }

    #[Test]
    public function omitsMatchedVariants(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        // Markeer accessoire-variant alsnog als matched → niet meer "missing".
        foreach ($bag['variants']->findAllForGroup('10013') as $variant) {
            self::assertNotNull($variant->id);
            if ($variant->accessoireItemcode === '60110') {
                $bag['variants']->markMatched($variant->id, '11111-60110');
            }
        }
        $bag['afas']->replaceSnapshot([]);

        $rows = ($bag['handler'])(new ListMissingVariants());

        self::assertSame([], $rows);
    }

    #[Test]
    public function emptyOutputForGroupsWithoutNoMatchVariants(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        $groups->save(new Group('Heartsine 350P', '10013'));

        $handler = new ListMissingVariantsHandler($groups, $variants, $items, $afas);

        self::assertSame([], $handler(new ListMissingVariants()));
    }

    /**
     * Bootstrap: groep met 1 base + 1 accessoire (60110). Base-only-variant is
     * matched ('11111'), accessoire-variant is no_match. AFAS-snapshot blijft
     * leeg — vul 'm per test naar smaak.
     *
     * @return array{
     *     handler: ListMissingVariantsHandler,
     *     afas: InMemoryAfasSamenstellingenRepository,
     *     variants: InMemoryGroupVariantRepository
     * }
     */
    private function wireGroupWithNoMatchAccessoireVariant(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'AED pakket NL', 'NL', '11111'));
        self::assertNotNull($base->id);
        $items->saveForBase($base->id, new GroupBaseItem('50013', 'AED NL'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10013', '60110');
        $variants->regenerateForGroup('10013');
        foreach ($variants->findAllForGroup('10013') as $variant) {
            self::assertNotNull($variant->id);
            if ($variant->accessoireItemcode === null) {
                $variants->markMatched($variant->id, '11111');
            } else {
                $variants->markNoMatch($variant->id);
            }
        }

        $handler = new ListMissingVariantsHandler($groups, $variants, $items, $afas);

        return ['handler' => $handler, 'afas' => $afas, 'variants' => $variants];
    }
}
