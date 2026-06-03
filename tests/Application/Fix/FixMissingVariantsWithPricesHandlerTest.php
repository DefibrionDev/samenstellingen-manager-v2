<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixMissingVariants;
use Defibrion\Samenstellingen\Application\Fix\FixMissingVariantsHandler;
use Defibrion\Samenstellingen\Application\Fix\FixMissingVariantsWithPricesHandler;
use Defibrion\Samenstellingen\Application\Fix\FixPriceMissingHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryBeginDateLookup;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryPriceFixWriter;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryVariantFixMissingWriter;
use Defibrion\Samenstellingen\Infrastructure\Fix\NullVariantSnapshotRefresher;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasArticleRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstWhitelistRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixMissingVariantsWithPricesHandlerTest extends TestCase
{
    #[Test]
    public function dryRunDoesNotRefreshOrInvokePrices(): void
    {
        $bag = $this->wiring();

        $result = ($bag['handler'])(new FixMissingVariants(apply: false));

        self::assertCount(1, $result->variants->plans);
        self::assertSame(0, $bag['refresher']->callCount);
        self::assertNull($result->prices);
    }

    #[Test]
    public function applyChainedRefreshesAndInvokesPriceFix(): void
    {
        $bag = $this->wiring();

        $result = ($bag['handler'])(new FixMissingVariants(apply: true));

        self::assertSame(1, $result->variants->appliedCount);
        self::assertSame(1, $bag['refresher']->callCount);
        self::assertNotNull($result->prices);
        self::assertSame(1, $result->prices->appliedCount);
        self::assertCount(1, $result->prices->plans);
        self::assertSame('11142-60110', $result->prices->plans[0]->variantItemcode);
    }

    #[Test]
    public function skipPricesSkipsRefreshAndPriceStep(): void
    {
        $bag = $this->wiring();

        $result = ($bag['handler'])(new FixMissingVariants(apply: true, skipPrices: true));

        self::assertSame(1, $result->variants->appliedCount);
        self::assertSame(0, $bag['refresher']->callCount);
        self::assertNull($result->prices);
    }

    #[Test]
    public function priceStepIsSkippedWhenNoVariantsWereApplied(): void
    {
        $bag = $this->wiring(noMissingVariants: true);

        $result = ($bag['handler'])(new FixMissingVariants(apply: true));

        self::assertSame(0, $result->variants->appliedCount);
        self::assertSame(0, $bag['refresher']->callCount);
        self::assertNull($result->prices);
    }

    /**
     * @return array{handler: FixMissingVariantsWithPricesHandler, refresher: NullVariantSnapshotRefresher}
     */
    private function wiring(bool $noMissingVariants = false): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $baseItems = new InMemoryGroupBaseItemRepository($bases);
        $afasSamenstellingen = new InMemoryAfasSamenstellingenRepository();
        $articles = new InMemoryAfasArticleRepository();
        $prijzen = new InMemoryAfasPrijsRepository();
        $prijslijsten = new InMemoryAfasPrijslijstRepository();
        $prijslijsten->replaceSnapshot([new AfasPrijslijst('*****', 'Basisprijslijst')]);
        $whitelist = new InMemoryPrijslijstWhitelistRepository();
        $whitelist->add('*****', 'test');

        $groups->save(new Group('Reanibex', '52112', 'Reanibex 100'));

        $base = $bases->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        if ($base->id !== null) {
            $baseItems->saveForBase($base->id, new GroupBaseItem('10142', 'Reanibex 100 NL'));
            $baseItems->saveForBase($base->id, new GroupBaseItem('70112', 'Reanimatiekit'));
        }

        $accessoires->save(new Accessoire('60110', 'Rugzak', 2500, naamKortNl: 'Rugtas'));
        $links->link('52112', '60110');

        $variants->regenerateForGroup('52112');
        foreach ($variants->findAllForGroup('52112') as $v) {
            if ($v->id === null) {
                continue;
            }
            if ($v->accessoireItemcode === null) {
                $variants->markMatched($v->id, '11142');
            } elseif (!$noMissingVariants) {
                $variants->markNoMatch($v->id);
            } else {
                // 'matched' op accessoire-variant zodat audit hem niet als missing rapporteert
                $variants->markMatched($v->id, '11142-60110');
            }
        }

        $afasSamenstellingen->replaceSnapshot([
            new AfasSamenstelling('11142', 'Base NL', '52112', ['10142', '70112']),
        ]);

        // Voor de price-stap: variant moet in afas_articles staan, en de base moet
        // een basisprijs hebben zodat de price-audit hem als 'missing' meldt voor de variant.
        $articles->replaceSnapshot([
            new AfasArticle('11142-60110', 'Variant NL'),
        ]);
        $prijzen->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
        ]);

        $variantsAudit = new ListMissingVariantsHandler($groups, $variants, $baseItems, $afasSamenstellingen);
        $variantsHandler = new FixMissingVariantsHandler(
            $variantsAudit,
            $groups,
            $bases,
            $variants,
            $accessoires,
            $afasSamenstellingen,
            new VariantNamingPolicy(),
            new InMemoryVariantFixMissingWriter(),
            new \Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWebsiteRepository(),
            new \Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryBasePublicationRepository(),
        );

        $beginDate = new InMemoryBeginDateLookup();
        $beginDate->set('11142', '*****', null, '2025-01-01');
        $priceAudit = new PriceAuditHandler($groups, $bases, $links, $prijzen, $prijslijsten, $whitelist);
        $pricesHandler = new FixPriceMissingHandler($priceAudit, $beginDate, $articles, new InMemoryPriceFixWriter());

        $refresher = new NullVariantSnapshotRefresher();
        $handler = new FixMissingVariantsWithPricesHandler($variantsHandler, $refresher, $pricesHandler);

        return ['handler' => $handler, 'refresher' => $refresher];
    }
}
