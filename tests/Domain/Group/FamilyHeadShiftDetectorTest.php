<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Group;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\FamilyHeadShiftDetector;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FamilyHeadShiftDetectorTest extends TestCase
{
    #[Test]
    public function detectsNoShiftsWhenAllParentsStable(): void
    {
        $groups = [new Group('Heartsine 350P', '10013')];
        $bases = [
            new GroupBase(1, 'NL', 'NL', '11111'),
            new GroupBase(2, 'FR', 'FR', '11112'),
            new GroupBase(3, 'UK', 'EN', '11113'),
        ];
        $afas = [
            new AfasSamenstelling('11111', 'NL', '10013', []),
            new AfasSamenstelling('11112', 'FR', '10013', []),
            new AfasSamenstelling('11113', 'UK', '10013', []),
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['10013' => $bases], $afas);

        self::assertSame([], $shifts);
    }

    #[Test]
    public function detectsShiftWhenThreeBasesPointToNewParent(): void
    {
        $groups = [new Group('Heartsine 350P', '10013')];
        $bases = [
            new GroupBase(1, 'NL', 'NL', '11111'),
            new GroupBase(2, 'FR', 'FR', '11112'),
            new GroupBase(3, 'UK', 'EN', '11113'),
        ];
        $afas = [
            new AfasSamenstelling('11111', 'NL', '10099', []),
            new AfasSamenstelling('11112', 'FR', '10099', []),
            new AfasSamenstelling('11113', 'UK', '10099', []),
            new AfasSamenstelling('10099', 'Nieuwe head', null, []),
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['10013' => $bases], $afas);

        self::assertCount(1, $shifts);
        self::assertSame('10013', $shifts[0]->oldHead);
        self::assertSame('10099', $shifts[0]->newHead);
        self::assertSame(3, $shifts[0]->baseCount);
    }

    #[Test]
    public function noShiftWhenAnyBaseStaysOnOldParent(): void
    {
        // Niet unanimous: 1 base wijst nog naar oude parent → block.
        $groups = [new Group('Heartsine 350P', '10013')];
        $bases = [
            new GroupBase(1, 'NL', 'NL', '11111'),
            new GroupBase(2, 'FR', 'FR', '11112'),
            new GroupBase(3, 'UK', 'EN', '11113'),
        ];
        $afas = [
            new AfasSamenstelling('11111', 'NL', '10099', []),
            new AfasSamenstelling('11112', 'FR', '10099', []),
            new AfasSamenstelling('11113', 'UK', '10013', []), // nog op oude head
            new AfasSamenstelling('10099', 'Niet relevant', null, []),
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['10013' => $bases], $afas);

        self::assertSame([], $shifts);
    }

    #[Test]
    public function shiftWithJustTwoBasesUnanimous(): void
    {
        // Geen drempel meer: 2 bases die allebei naar nieuwe parent wijzen volstaan.
        $groups = [new Group('CU Medical', '064.1308-SAM-UK')];
        $bases = [
            new GroupBase(1, 'DE', 'DE', '064.1308-SAM-DE'),
            new GroupBase(2, 'UK', 'EN', '064.1309-SAM-UK'),
        ];
        $afas = [
            new AfasSamenstelling('064.1308-SAM-DE', 'DE', '064.1309-SAM-UK', []),
            new AfasSamenstelling('064.1309-SAM-UK', 'UK', '064.1309-SAM-UK', []), // self-ref
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['064.1308-SAM-UK' => $bases], $afas);

        self::assertCount(1, $shifts);
        self::assertSame('064.1308-SAM-UK', $shifts[0]->oldHead);
        self::assertSame('064.1309-SAM-UK', $shifts[0]->newHead);
        self::assertSame(2, $shifts[0]->baseCount);
    }

    #[Test]
    public function noShiftWhenBasesDisagreeOnNewParent(): void
    {
        // Verschillende bases wijzen naar verschillende nieuwe parents → block.
        $groups = [new Group('Heartsine 350P', '10013')];
        $bases = [
            new GroupBase(1, 'NL', 'NL', '11111'),
            new GroupBase(2, 'FR', 'FR', '11112'),
            new GroupBase(3, 'UK', 'EN', '11113'),
        ];
        $afas = [
            new AfasSamenstelling('11111', 'NL', '10099', []),
            new AfasSamenstelling('11112', 'FR', '10099', []),
            new AfasSamenstelling('11113', 'UK', '10088', []), // andere nieuwe parent
            new AfasSamenstelling('10099', 'A', null, []),
            new AfasSamenstelling('10088', 'B', null, []),
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['10013' => $bases], $afas);

        self::assertSame([], $shifts);
    }

    #[Test]
    public function noShiftWhenNewParentDoesNotExistInSnapshot(): void
    {
        $groups = [new Group('Heartsine 350P', '10013')];
        $bases = [
            new GroupBase(1, 'NL', 'NL', '11111'),
            new GroupBase(2, 'FR', 'FR', '11112'),
            new GroupBase(3, 'UK', 'EN', '11113'),
        ];
        $afas = [
            new AfasSamenstelling('11111', 'NL', '10099', []),
            new AfasSamenstelling('11112', 'FR', '10099', []),
            new AfasSamenstelling('11113', 'UK', '10099', []),
            // 10099 zelf staat NIET in de snapshot — ongeldige nieuwe head.
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['10013' => $bases], $afas);

        self::assertSame([], $shifts);
    }

    #[Test]
    public function noShiftWhenNewParentAlreadyClaimedByOtherGroup(): void
    {
        $groups = [
            new Group('Heartsine 350P', '10013'),
            new Group('Andere groep', '10099'),
        ];
        $bases = [
            new GroupBase(1, 'NL', 'NL', '11111'),
            new GroupBase(2, 'FR', 'FR', '11112'),
            new GroupBase(3, 'UK', 'EN', '11113'),
        ];
        $afas = [
            new AfasSamenstelling('11111', 'NL', '10099', []),
            new AfasSamenstelling('11112', 'FR', '10099', []),
            new AfasSamenstelling('11113', 'UK', '10099', []),
            new AfasSamenstelling('10099', 'Andere groep head', null, []),
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['10013' => $bases], $afas);

        self::assertSame([], $shifts);
    }

    #[Test]
    public function ignoresBasesWithoutAfasItemcode(): void
    {
        // Bases zonder SKU tellen niet mee — wel een shift omdat de SKU-bases
        // unanimous zijn (de SKU-loze base is "geen vote", niet "tegenstem").
        $groups = [new Group('Heartsine 350P', '10013')];
        $bases = [
            new GroupBase(1, 'NL', 'NL', '11111'),
            new GroupBase(2, 'FR', 'FR', '11112'),
            new GroupBase(3, 'no SKU', 'NL', null),
        ];
        $afas = [
            new AfasSamenstelling('11111', 'NL', '10099', []),
            new AfasSamenstelling('11112', 'FR', '10099', []),
            new AfasSamenstelling('10099', 'Niet relevant', null, []),
        ];

        $shifts = (new FamilyHeadShiftDetector())->detect($groups, ['10013' => $bases], $afas);

        self::assertCount(1, $shifts);
        self::assertSame('10013', $shifts[0]->oldHead);
        self::assertSame('10099', $shifts[0]->newHead);
        self::assertSame(2, $shifts[0]->baseCount);
    }
}
