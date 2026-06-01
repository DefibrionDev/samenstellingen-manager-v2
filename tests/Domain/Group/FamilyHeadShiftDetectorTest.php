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
    public function noShiftWhenFewerThanThreeBasesAgree(): void
    {
        // Slechts 2 bases zijn verschoven — niet boven de drempel.
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
        // Bases zonder SKU tellen niet mee in de shift-detectie.
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

        // 2 bases verschoven (de SKU-loze telt niet) — onder de drempel.
        self::assertSame([], $shifts);
    }
}
