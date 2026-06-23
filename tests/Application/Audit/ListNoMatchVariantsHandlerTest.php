<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariants;
use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariantsHandler;
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

final class ListNoMatchVariantsHandlerTest extends TestCase
{
    #[Test]
    public function reportsNoMatchVariantWithExpectedBomAndSuggestedItemcode(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        $bag['afas']->replaceSnapshot([]);

        $rows = ($bag['handler'])(new ListNoMatchVariants());

        self::assertCount(1, $rows);
        self::assertSame('Heartsine 350P', $rows[0]->groep);
        self::assertSame('60110', $rows[0]->accessoireItemcode);
        self::assertSame(['50013', '60110'], $rows[0]->verwachteBom);
        self::assertSame('11111-60110', $rows[0]->verwachteItemcode);
    }

    #[Test]
    public function flagsThatAfasCompositionWithExpectedItemcodeAlreadyExists(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        // De samenstelling 11111-60110 bestaat in AFAS (BOM mag inhoudelijk afwijken) —
        // de variant is no_match maar de compositie is er wel degelijk.
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11111-60110', 'Variant met Rugzak', '11111', ['50013']),
        ]);

        $rows = ($bag['handler'])(new ListNoMatchVariants());

        self::assertCount(1, $rows);
        self::assertSame('11111-60110', $rows[0]->bestaandeAfasItemcode);
        // Geen compositie met exact de verwachte BOM (50013+60110) → leeg.
        self::assertNull($rows[0]->exacteBomMatchItemcode);
    }

    #[Test]
    public function reportsWhichItemcodesAreMissingOrExtraInExistingComposition(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        // Verwachte BOM = [50013, 60110]. De bestaande compositie heeft [50013, 70112]:
        // mist 60110, en heeft 70112 teveel.
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11111-60110', 'Variant met Rugzak', '11111', ['50013', '70112']),
        ]);

        $rows = ($bag['handler'])(new ListNoMatchVariants());

        self::assertCount(1, $rows);
        self::assertSame('11111-60110', $rows[0]->bestaandeAfasItemcode);
        self::assertSame(['60110'], $rows[0]->ontbrekendeItemcodes);
        self::assertSame(['70112'], $rows[0]->extraItemcodes);
    }

    #[Test]
    public function bomDiffIsEmptyWhenNoExistingComposition(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        $bag['afas']->replaceSnapshot([]);

        $rows = ($bag['handler'])(new ListNoMatchVariants());

        self::assertCount(1, $rows);
        self::assertSame([], $rows[0]->ontbrekendeItemcodes);
        self::assertSame([], $rows[0]->extraItemcodes);
    }

    #[Test]
    public function bestaandeAfasItemcodeIsNullWhenCompositionAbsent(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        $bag['afas']->replaceSnapshot([]);

        $rows = ($bag['handler'])(new ListNoMatchVariants());

        self::assertCount(1, $rows);
        self::assertNull($rows[0]->bestaandeAfasItemcode);
    }

    #[Test]
    public function flagsCompositionThatHasExactlyTheExpectedBom(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        // Een compositie met exact de verwachte itemcodes (50013 + 60110), onder een
        // afwijkende itemcode. In productie is dit typisch een duplicaat-compositie.
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('ALT-CODE', 'Zelfde inhoud, andere code', '11111', ['50013', '60110']),
        ]);

        $rows = ($bag['handler'])(new ListNoMatchVariants());

        self::assertCount(1, $rows);
        self::assertSame('ALT-CODE', $rows[0]->exacteBomMatchItemcode);
        // De verwachte itemcode 11111-60110 bestaat niet → bestaande blijft leeg.
        self::assertNull($rows[0]->bestaandeAfasItemcode);
    }

    #[Test]
    public function omitsMatchedVariants(): void
    {
        $bag = $this->wireGroupWithNoMatchAccessoireVariant();
        foreach ($bag['variants']->findAllForGroup('10013') as $variant) {
            self::assertNotNull($variant->id);
            if ($variant->accessoireItemcode === '60110') {
                $bag['variants']->markMatched($variant->id, '11111-60110');
            }
        }
        $bag['afas']->replaceSnapshot([]);

        $rows = ($bag['handler'])(new ListNoMatchVariants());

        self::assertSame([], $rows);
    }

    /**
     * Groep met 1 base (afas-sku 11111, base-item 50013) + 1 accessoire (60110).
     * Base-only-variant is matched ('11111'), accessoire-variant is no_match.
     *
     * @return array{
     *     handler: ListNoMatchVariantsHandler,
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

        $handler = new ListNoMatchVariantsHandler($groups, $variants, $items, $afas);

        return ['handler' => $handler, 'afas' => $afas, 'variants' => $variants];
    }
}
