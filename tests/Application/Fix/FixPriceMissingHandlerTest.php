<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixPriceMissing;
use Defibrion\Samenstellingen\Application\Fix\FixPriceMissingHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryBeginDateLookup;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryPriceFixWriter;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasArticleRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstWhitelistRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixPriceMissingHandlerTest extends TestCase
{
    #[Test]
    public function plansInsertWhenArticleExistsAndPriceMissing(): void
    {
        $bag = $this->wiring();
        $bag['articles']->replaceSnapshot([new AfasArticle('11142-60110', 'Variant')]);

        $result = ($bag['handler'])(new FixPriceMissing(apply: false));

        self::assertCount(1, $result->plans);
        self::assertSame([], $result->skippedNoArticle);
        self::assertSame(192400, $result->plans[0]->targetCents); // 189900 + 2500
    }

    #[Test]
    public function skipsVariantsThatDoNotExistAsArticle(): void
    {
        $bag = $this->wiring();
        // Geen artikel toegevoegd

        $result = ($bag['handler'])(new FixPriceMissing(apply: false));

        self::assertSame([], $result->plans);
        self::assertSame(['11142-60110'], $result->skippedNoArticle);
    }

    #[Test]
    public function applyInsertsAllPlans(): void
    {
        $bag = $this->wiring();
        $bag['articles']->replaceSnapshot([new AfasArticle('11142-60110', 'Variant')]);

        $result = ($bag['handler'])(new FixPriceMissing(apply: true));

        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $bag['writer']->inserted);
        self::assertSame([], $bag['writer']->applied); // geen updates
    }

    /**
     * @return array{
     *   handler: FixPriceMissingHandler,
     *   writer: InMemoryPriceFixWriter,
     *   articles: InMemoryAfasArticleRepository
     * }
     */
    private function wiring(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $prijzen = new InMemoryAfasPrijsRepository();
        $prijslijsten = new InMemoryAfasPrijslijstRepository();
        $prijslijsten->replaceSnapshot([new AfasPrijslijst('*****', 'Basisprijslijst')]);
        $whitelist = new InMemoryPrijslijstWhitelistRepository();
        $whitelist->add('*****', 'test');
        $articles = new InMemoryAfasArticleRepository();

        $groups->save(new Group('Reanibex', '52112'));
        $bases->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $accessoires->save(new Accessoire('60110', 'Rugzak', 2500));
        $links->link('52112', '60110');

        // Base heeft prijs in *****, variant NIET → missing
        $prijzen->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
        ]);

        $audit = new PriceAuditHandler($groups, $bases, $links, $prijzen, $prijslijsten, $whitelist);
        $lookup = new InMemoryBeginDateLookup();
        $lookup->set('11142', '*****', null, '2025-01-01');
        $writer = new InMemoryPriceFixWriter();
        $handler = new FixPriceMissingHandler($audit, $lookup, $articles, $writer);

        return ['handler' => $handler, 'writer' => $writer, 'articles' => $articles];
    }
}
