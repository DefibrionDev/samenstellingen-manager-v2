<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditStickers;
use Defibrion\Samenstellingen\Application\Audit\StickerAuditHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StickerAuditHandlerTest extends TestCase
{
    #[Test]
    public function noDriftWhenStickerMatchesLanguage(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Test', '11142'));
        $bag['bases']->saveForGroup('11142', new GroupBase(null, 'Base NL', 'NL', '11142'));
        $bag['samenstellingen']->replaceSnapshot([
            new AfasSamenstelling('11142', 'Base NL', null, ['10142', '70112', '81111']),
        ]);

        self::assertSame([], ($bag['handler'])(new AuditStickers()));
    }

    #[Test]
    public function reportsDriftWhenWrongSticker(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Test', '11142'));
        $bag['bases']->saveForGroup('11142', new GroupBase(null, 'Base NL', 'NL', '11142'));
        // NL-base met FR-sticker → drift
        $bag['samenstellingen']->replaceSnapshot([
            new AfasSamenstelling('11142', 'Base NL', null, ['10142', '70112', '81211']),
        ]);

        $rows = ($bag['handler'])(new AuditStickers());

        self::assertCount(1, $rows);
        self::assertSame('81111', $rows[0]->expectedSticker);
        self::assertSame(['81211'], $rows[0]->actualStickers);
    }

    #[Test]
    public function compoundLanguageUsesFirstToken(): void
    {
        // NL/FR/EN → eerste = NL → verwacht 81111. Heeft 81211 → drift.
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Test', '21020'));
        $bag['bases']->saveForGroup('21020', new GroupBase(null, 'Mindray', 'NL/FR/EN', '21020'));
        $bag['samenstellingen']->replaceSnapshot([
            new AfasSamenstelling('21020', 'Mindray', null, ['20020', '70112', '81211']),
        ]);

        $rows = ($bag['handler'])(new AuditStickers());

        self::assertCount(1, $rows);
        self::assertSame('81111', $rows[0]->expectedSticker);
    }

    #[Test]
    public function englishBaseExpectsInternational(): void
    {
        // EN → 81611 (geen sticker-mapping). Base heeft 81111 → drift.
        $bag = $this->wiring();
        $bag['groups']->save(new Group('UK', '11113'));
        $bag['bases']->saveForGroup('11113', new GroupBase(null, 'PAD UK', 'EN', '11113'));
        $bag['samenstellingen']->replaceSnapshot([
            new AfasSamenstelling('11113', 'PAD UK', null, ['10113', '70112', '81111']),
        ]);

        $rows = ($bag['handler'])(new AuditStickers());

        self::assertCount(1, $rows);
        self::assertSame('81611', $rows[0]->expectedSticker);
        self::assertSame(['81111'], $rows[0]->actualStickers);
    }

    #[Test]
    public function skipsBasesWithoutAfasSamenstelling(): void
    {
        $bag = $this->wiring();
        $bag['groups']->save(new Group('Test', '99999'));
        $bag['bases']->saveForGroup('99999', new GroupBase(null, 'Mystery', 'NL', '99999'));
        // Geen samenstelling in snapshot → skip

        self::assertSame([], ($bag['handler'])(new AuditStickers()));
    }

    /**
     * @return array{
     *   groups: InMemoryGroupRepository,
     *   bases: InMemoryGroupBaseRepository,
     *   samenstellingen: InMemoryAfasSamenstellingenRepository,
     *   handler: StickerAuditHandler
     * }
     */
    private function wiring(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $samenstellingen = new InMemoryAfasSamenstellingenRepository();
        $handler = new StickerAuditHandler($groups, $bases, $samenstellingen);

        return compact('groups', 'bases', 'samenstellingen', 'handler');
    }
}
