<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditFamilyHeadParent;
use Defibrion\Samenstellingen\Application\Audit\FamilyHeadParentAuditHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FamilyHeadParentAuditHandlerTest extends TestCase
{
    #[Test]
    public function reportsFamilyHeadsWithoutItemcodeParent(): void
    {
        $bag = $this->scaffold([
            'Mindray C2 vol' => '21014',
            'Cardiac G5 semi' => '11148',
        ], [
            new AfasSamenstelling('21014', 'Mindray C2 vol', null, []),
            new AfasSamenstelling('11148', 'Cardiac G5 semi', null, []),
        ]);

        $rows = ($bag['handler'])(new AuditFamilyHeadParent());

        self::assertCount(2, $rows);
        $found = null;
        foreach ($rows as $row) {
            if ($row->familyHead === '21014') {
                $found = $row;
            }
        }
        self::assertNotNull($found);
        self::assertNull($found->currentParent);
        self::assertSame('21014', $found->expectedParent);
        self::assertSame('Mindray C2 vol', $found->groupName);
    }

    #[Test]
    public function skipsFamilyHeadsWithSelfParent(): void
    {
        $bag = $this->scaffold([
            'Heartsine PAD 350P' => '11111',
        ], [
            new AfasSamenstelling('11111', 'Heartsine', '11111', []),
        ]);

        self::assertSame([], ($bag['handler'])(new AuditFamilyHeadParent()));
    }

    #[Test]
    public function reportsFamilyHeadsWithDeviantParent(): void
    {
        // Een head die naar een ander itemcode wijst (zou niet mogen) is ook drift.
        $bag = $this->scaffold([
            'Mindray C1 semi' => '21018',
        ], [
            new AfasSamenstelling('21018', 'Mindray C1 semi', '21017', []),
        ]);

        $rows = ($bag['handler'])(new AuditFamilyHeadParent());

        self::assertCount(1, $rows);
        self::assertSame('21017', $rows[0]->currentParent);
        self::assertSame('21018', $rows[0]->expectedParent);
    }

    #[Test]
    public function skipsGroupsWhereFamilyHeadIsAbsentFromAfasSnapshot(): void
    {
        // Geen AFAS-record voor de head → niets te zeggen over de parent → geen drift-rij.
        $bag = $this->scaffold(['Onbekend' => '99999'], []);

        self::assertSame([], ($bag['handler'])(new AuditFamilyHeadParent()));
    }

    /**
     * @param array<string, string>      $groups        groupName → familyHead
     * @param list<AfasSamenstelling>    $samenstellingen
     *
     * @return array{handler: FamilyHeadParentAuditHandler}
     */
    private function scaffold(array $groups, array $samenstellingen): array
    {
        $groupRepo = new InMemoryGroupRepository();
        foreach ($groups as $name => $head) {
            $groupRepo->save(new Group($name, $head));
        }
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot($samenstellingen);

        return ['handler' => new FamilyHeadParentAuditHandler($groupRepo, $afas)];
    }
}
