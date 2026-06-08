<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditBaseParent;
use Defibrion\Samenstellingen\Application\Audit\BaseParentAuditHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BaseParentAuditHandlerTest extends TestCase
{
    #[Test]
    public function reportsNonHeadBasesWithEmptyItemcodeParent(): void
    {
        $bag = $this->scaffold(
            groups: [['Lifepak CR2 semi', '11161']],
            bases: [
                ['11161', '11161', 'NL'],     // family-head zelf — gefilterd door slice 52
                ['11161', '11164', 'NL/EN'], // non-head base, parent leeg → drift
                ['11161', '11155', 'NL'],    // non-head, parent leeg → drift
            ],
            snapshot: [
                new AfasSamenstelling('11161', 'Lifepak CR2 semi NL', '11161', []),
                new AfasSamenstelling('11164', 'Lifepak CR2 semi WiFi', null, []),
                new AfasSamenstelling('11155', 'Lifepak CR2 semi wifi', null, []),
            ],
        );

        $rows = ($bag['handler'])(new AuditBaseParent());

        self::assertCount(2, $rows);
        $found11164 = null;
        $found11155 = null;
        foreach ($rows as $row) {
            if ($row->afasItemcode === '11164') {
                $found11164 = $row;
            }
            if ($row->afasItemcode === '11155') {
                $found11155 = $row;
            }
        }
        self::assertNotNull($found11164);
        self::assertNotNull($found11155);
        self::assertNull($found11164->currentParent);
        self::assertSame('11161', $found11164->expectedParent);
        self::assertSame('Lifepak CR2 semi', $found11164->groupName);
        self::assertSame('NL/EN', $found11164->languageCode);
    }

    #[Test]
    public function skipsBasesWhereParentMatchesFamilyHead(): void
    {
        $bag = $this->scaffold(
            groups: [['Lifepak CR2 semi', '11161']],
            bases: [
                ['11161', '11164', 'NL/EN'],
            ],
            snapshot: [
                new AfasSamenstelling('11164', 'Lifepak CR2 WiFi', '11161', []),
            ],
        );

        self::assertSame([], ($bag['handler'])(new AuditBaseParent()));
    }

    #[Test]
    public function reportsBasesWithDeviantParent(): void
    {
        $bag = $this->scaffold(
            groups: [['Mindray C1 semi', '21018']],
            bases: [
                ['21018', '21011', 'NL/EN/FR'],
            ],
            snapshot: [
                new AfasSamenstelling('21011', 'Mindray C1 3-talig', '21017', []),
            ],
        );

        $rows = ($bag['handler'])(new AuditBaseParent());

        self::assertCount(1, $rows);
        self::assertSame('21011', $rows[0]->afasItemcode);
        self::assertSame('21017', $rows[0]->currentParent);
        self::assertSame('21018', $rows[0]->expectedParent);
    }

    #[Test]
    public function skipsBasesAbsentFromAfasSnapshot(): void
    {
        $bag = $this->scaffold(
            groups: [['Onbekend', '11111']],
            bases: [
                ['11111', '11112', 'FR'],
            ],
            snapshot: [],
        );

        self::assertSame([], ($bag['handler'])(new AuditBaseParent()));
    }

    /**
     * @param list<array{0: string, 1: string}>           $groups   [groupName, familyHead]
     * @param list<array{0: string, 1: string, 2: string}> $bases    [familyHead, afasItemcode, languageCode]
     * @param list<AfasSamenstelling>                      $snapshot
     *
     * @return array{handler: BaseParentAuditHandler}
     */
    private function scaffold(array $groups, array $bases, array $snapshot): array
    {
        $groupRepo = new InMemoryGroupRepository();
        foreach ($groups as $g) {
            $groupRepo->save(new Group($g[0], $g[1]));
        }
        $baseRepo = new InMemoryGroupBaseRepository($groupRepo);
        foreach ($bases as $b) {
            $baseRepo->saveForGroup($b[0], new GroupBase(null, 'naam-' . $b[1], $b[2], $b[1]));
        }
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot($snapshot);

        return ['handler' => new BaseParentAuditHandler($groupRepo, $baseRepo, $afas)];
    }
}
