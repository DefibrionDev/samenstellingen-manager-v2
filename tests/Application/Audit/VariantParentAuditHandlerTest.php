<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditVariantParent;
use Defibrion\Samenstellingen\Application\Audit\VariantParentAuditHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VariantParentAuditHandlerTest extends TestCase
{
    #[Test]
    public function variantWithSelfMatchingParentIsNoDrift(): void
    {
        $bag = $this->scaffold(snapshot: [
            new AfasSamenstelling('11043', 'Defibtech VIEW semi NL', '11043', []),
            new AfasSamenstelling('11043-60110', 'NL + Backpack', '11043', []),
        ]);

        self::assertSame([], ($bag['handler'])(new AuditVariantParent()));
    }

    #[Test]
    public function variantWithEmptyParentIsDrift(): void
    {
        $bag = $this->scaffold(snapshot: [
            new AfasSamenstelling('11043', 'Defibtech VIEW semi NL', '11043', []),
            new AfasSamenstelling('11043-60110', 'NL + Backpack', null, []),
        ]);

        $rows = ($bag['handler'])(new AuditVariantParent());

        self::assertCount(1, $rows);
        self::assertSame('11043-60110', $rows[0]->afasItemcode);
        self::assertNull($rows[0]->currentParent);
        self::assertSame('11043', $rows[0]->expectedParent);
        self::assertSame('Defibtech VIEW semi', $rows[0]->groupName);
    }

    #[Test]
    public function variantWithDeviantParentIsDrift(): void
    {
        $bag = $this->scaffold(snapshot: [
            new AfasSamenstelling('11043', 'Defibtech VIEW semi NL', '11043', []),
            new AfasSamenstelling('11043-60110', 'NL + Backpack', '99999', []),
        ]);

        $rows = ($bag['handler'])(new AuditVariantParent());

        self::assertCount(1, $rows);
        self::assertSame('99999', $rows[0]->currentParent);
        self::assertSame('11043', $rows[0]->expectedParent);
    }

    #[Test]
    public function variantAbsentFromSnapshotIsSkipped(): void
    {
        // Variant gematcht maar het AFAS-record bestaat niet (snapshot leeg voor 'm) — niets te zeggen.
        $bag = $this->scaffold(snapshot: [
            new AfasSamenstelling('11043', 'Defibtech VIEW semi NL', '11043', []),
            // 11043-60110 ontbreekt in snapshot
        ]);

        self::assertSame([], ($bag['handler'])(new AuditVariantParent()));
    }

    /**
     * Scaffold: 1 group "Defibtech VIEW semi" (head=11043, base 11043), accessoire 60110
     * gelinkt, variant matched naar 11043-60110.
     *
     * @param list<AfasSamenstelling> $snapshot
     *
     * @return array{handler: VariantParentAuditHandler}
     */
    private function scaffold(array $snapshot): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        $groups->save(new Group('Defibtech VIEW semi', '11043'));
        $base = $bases->saveForGroup('11043', new GroupBase(null, 'NL', 'NL', '11043'));
        self::assertNotNull($base->id);
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('11043', '60110');
        $variants->regenerateForGroup('11043');
        foreach ($variants->findAllForGroup('11043') as $variant) {
            self::assertNotNull($variant->id);
            if ($variant->accessoireItemcode === '60110') {
                $variants->markMatched($variant->id, '11043-60110');
            }
        }

        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot($snapshot);

        return [
            'handler' => new VariantParentAuditHandler($groups, $bases, $variants, $afas),
        ];
    }
}
