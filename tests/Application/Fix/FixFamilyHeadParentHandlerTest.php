<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\FamilyHeadParentAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixFamilyHeadParent;
use Defibrion\Samenstellingen\Application\Fix\FixFamilyHeadParentHandler;
use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriteFailedException;
use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriter;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FixFamilyHeadParentHandlerTest extends TestCase
{
    #[Test]
    public function dryRunListsPlansAndSkippedWithoutInvokingWriter(): void
    {
        $writer = new FamilyHeadRecordingItemcodeParentWriter();
        $handler = $this->makeHandler($writer, [
            new AfasSamenstelling('11148', 'Cardiac G5', null, []),     // leeg → plan
            new AfasSamenstelling('11197', 'Lifepak CR2 vol', null, []), // leeg → plan
            new AfasSamenstelling('21018', 'Mindray C1 semi', '21017', []), // afwijkend → skipped
        ]);

        $result = $handler(new FixFamilyHeadParent(apply: false));

        self::assertCount(2, $result->plans);
        self::assertCount(1, $result->skippedFilled);
        self::assertSame(0, $result->applied);
        self::assertSame([], $writer->writes);
    }

    #[Test]
    public function applyInvokesWriterPerPlanOnly(): void
    {
        $writer = new FamilyHeadRecordingItemcodeParentWriter();
        $handler = $this->makeHandler($writer, [
            new AfasSamenstelling('11148', 'Cardiac G5', null, []),
            new AfasSamenstelling('21018', 'Mindray C1 semi', '21017', []),
        ]);

        $result = $handler(new FixFamilyHeadParent(apply: true));

        self::assertSame(1, $result->applied);
        self::assertSame([['11148', '11148']], $writer->writes);
        // 21018 wordt nooit ge-PUT, ook niet in apply-mode.
        self::assertCount(1, $result->skippedFilled);
    }

    #[Test]
    public function applyReportsWriterFailures(): void
    {
        $writer = new FamilyHeadFailingItemcodeParentWriter('AFAS schreeuwt');
        $handler = $this->makeHandler($writer, [
            new AfasSamenstelling('11148', 'Cardiac G5', null, []),
        ]);

        $result = $handler(new FixFamilyHeadParent(apply: true));

        self::assertSame(0, $result->applied);
        self::assertCount(1, $result->failures);
        self::assertSame('11148', $result->failures[0]['plan']->familyHead);
        self::assertStringContainsString('AFAS schreeuwt', $result->failures[0]['error']);
    }

    #[Test]
    public function emptyAuditResultYieldsEmptyResult(): void
    {
        $writer = new FamilyHeadRecordingItemcodeParentWriter();
        $handler = $this->makeHandler($writer, [
            new AfasSamenstelling('11111', 'PAD 350P', '11111', []), // self ✓
        ]);

        $result = $handler(new FixFamilyHeadParent(apply: true));

        self::assertSame([], $result->plans);
        self::assertSame([], $result->skippedFilled);
        self::assertSame(0, $result->applied);
        self::assertSame([], $result->failures);
        self::assertSame([], $writer->writes);
    }

    /**
     * @param list<AfasSamenstelling> $snapshot
     */
    private function makeHandler(ItemcodeParentWriter $writer, array $snapshot): FixFamilyHeadParentHandler
    {
        $groups = new InMemoryGroupRepository();
        foreach ($snapshot as $s) {
            $groups->save(new Group($s->name, $s->itemcode));
        }
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot($snapshot);

        return new FixFamilyHeadParentHandler(
            new FamilyHeadParentAuditHandler($groups, $afas),
            $writer,
        );
    }
}

final class FamilyHeadRecordingItemcodeParentWriter implements ItemcodeParentWriter
{
    /** @var list<array{0: string, 1: string}> */
    public array $writes = [];

    public function write(string $itemcode, string $parent): void
    {
        $this->writes[] = [$itemcode, $parent];
    }
}

final readonly class FamilyHeadFailingItemcodeParentWriter implements ItemcodeParentWriter
{
    public function __construct(private string $message)
    {
    }

    public function write(string $itemcode, string $parent): void
    {
        throw ItemcodeParentWriteFailedException::from($itemcode, new RuntimeException($this->message));
    }
}
