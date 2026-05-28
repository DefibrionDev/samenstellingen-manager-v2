<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixPriceDrift;
use Defibrion\Samenstellingen\Application\Fix\FixPriceDriftHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryBeginDateLookup;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryPriceFixWriter;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstWhitelistRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixPriceDriftHandlerTest extends TestCase
{
    #[Test]
    public function dryRunCountsPlansButDoesNotWrite(): void
    {
        $bag = $this->wiringWithDrift();

        $result = ($bag['handler'])(new FixPriceDrift(apply: false));

        self::assertCount(1, $result->plans);
        self::assertSame(0, $result->appliedCount);
        self::assertSame([], $bag['writer']->applied);
    }

    #[Test]
    public function applyWritesAllPlans(): void
    {
        $bag = $this->wiringWithDrift();

        $result = ($bag['handler'])(new FixPriceDrift(apply: true));

        self::assertCount(1, $result->plans);
        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $bag['writer']->applied);
        $written = $bag['writer']->applied[0];
        self::assertSame('11142-60110', $written->variantItemcode);
        self::assertSame(192400, $written->targetCents); // 189900 + 2500
    }

    #[Test]
    public function limitCapsPlanCount(): void
    {
        $bag = $this->wiringWithMultipleDrift();

        $result = ($bag['handler'])(new FixPriceDrift(apply: true, limit: 1));

        self::assertCount(1, $result->plans);
        self::assertSame(1, $result->appliedCount);
    }

    #[Test]
    public function failedWriteDoesNotBlockNextPlan(): void
    {
        $bag = $this->wiringWithMultipleDrift(failOnVariant: '11142-60110');

        $result = ($bag['handler'])(new FixPriceDrift(apply: true));

        self::assertCount(2, $result->plans);
        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $result->failures);
        self::assertSame('11142-60110', $result->failures[0]['plan']->variantItemcode);
    }

    /**
     * @return array{handler: FixPriceDriftHandler, writer: InMemoryPriceFixWriter}
     */
    private function wiringWithDrift(): array
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

        $groups->save(new Group('Reanibex', '52112'));
        $bases->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $accessoires->save(new Accessoire('60110', 'Rugzak', 2500));
        $links->link('52112', '60110');

        $prijzen->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 193000, null, '2025-01-01', null), // drift €31
        ]);

        $audit = new PriceAuditHandler($groups, $bases, $links, $prijzen, $prijslijsten, $whitelist);
        $lookup = new InMemoryBeginDateLookup();
        $lookup->set('11142-60110', '*****', null, '2025-01-01');
        $writer = new InMemoryPriceFixWriter();
        $handler = new FixPriceDriftHandler($audit, $lookup, $writer);

        return ['handler' => $handler, 'writer' => $writer];
    }

    /**
     * @return array{handler: FixPriceDriftHandler, writer: InMemoryPriceFixWriter}
     */
    private function wiringWithMultipleDrift(?string $failOnVariant = null): array
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

        $groups->save(new Group('Reanibex', '52112'));
        $bases->saveForGroup('52112', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $accessoires->save(new Accessoire('60110', 'Rugzak', 2500));
        $accessoires->save(new Accessoire('60112', 'Witte binnenkast', 29500));
        $links->link('52112', '60110');
        $links->link('52112', '60112');

        $prijzen->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 189900, null, '2025-01-01', null),
            new AfasPrijs('11142-60110', '*****', null, 193000, null, '2025-01-01', null),
            new AfasPrijs('11142-60112', '*****', null, 222000, null, '2025-01-01', null), // drift
        ]);

        $audit = new PriceAuditHandler($groups, $bases, $links, $prijzen, $prijslijsten, $whitelist);
        $lookup = new InMemoryBeginDateLookup();
        $lookup->set('11142-60110', '*****', null, '2025-01-01');
        $lookup->set('11142-60112', '*****', null, '2025-01-01');
        $writer = new InMemoryPriceFixWriter($failOnVariant);
        $handler = new FixPriceDriftHandler($audit, $lookup, $writer);

        return ['handler' => $handler, 'writer' => $writer];
    }
}
