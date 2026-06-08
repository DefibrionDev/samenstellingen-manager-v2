<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\BaseParentAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixBaseParent;
use Defibrion\Samenstellingen\Application\Fix\FixBaseParentHandler;
use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriteFailedException;
use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriter;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FixBaseParentHandlerTest extends TestCase
{
    #[Test]
    public function dryRunListsPlansAndSkippedWithoutInvokingWriter(): void
    {
        $writer = new RecordingItemcodeParentWriter();
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11161', 'Lifepak CR2 semi NL', '11161', []),
            new AfasSamenstelling('11164', 'Lifepak CR2 semi WiFi', null, []),     // leeg → plan
            new AfasSamenstelling('11155', 'Lifepak CR2 semi wifi', null, []),     // leeg → plan
            new AfasSamenstelling('21011', 'Mindray C1 3-talig', '21017', []),     // afwijkend → skip
            new AfasSamenstelling('21018', 'Mindray C1 head', '21018', []),
        ]);

        $result = $handler(new FixBaseParent(apply: false));

        self::assertCount(2, $result->plans);
        self::assertCount(1, $result->skippedFilled);
        self::assertSame(0, $result->applied);
        self::assertSame([], $writer->writes);
    }

    #[Test]
    public function applyInvokesWriterPerPlanOnly(): void
    {
        $writer = new RecordingItemcodeParentWriter();
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11161', 'Head', '11161', []),
            new AfasSamenstelling('11164', 'Lifepak CR2 WiFi', null, []),
            new AfasSamenstelling('21018', 'Head Mindray', '21018', []),
            new AfasSamenstelling('21011', 'Mindray 3-talig', '21017', []),
        ]);

        $result = $handler(new FixBaseParent(apply: true));

        self::assertSame(1, $result->applied);
        self::assertSame([['11164', '11161']], $writer->writes);
        self::assertCount(1, $result->skippedFilled);
    }

    #[Test]
    public function applyReportsWriterFailures(): void
    {
        $writer = new FailingItemcodeParentWriter('AFAS gooit 500');
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11161', 'Head', '11161', []),
            new AfasSamenstelling('11164', 'Lifepak CR2 WiFi', null, []),
        ]);

        $result = $handler(new FixBaseParent(apply: true));

        self::assertSame(0, $result->applied);
        self::assertCount(1, $result->failures);
        self::assertSame('11164', $result->failures[0]['plan']->afasItemcode);
        self::assertStringContainsString('AFAS gooit 500', $result->failures[0]['error']);
    }

    #[Test]
    public function emptyAuditResultYieldsEmptyResult(): void
    {
        $writer = new RecordingItemcodeParentWriter();
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11161', 'Head', '11161', []),
            new AfasSamenstelling('11164', 'WiFi', '11161', []), // al goed
        ]);

        $result = $handler(new FixBaseParent(apply: true));

        self::assertSame([], $result->plans);
        self::assertSame([], $result->skippedFilled);
        self::assertSame(0, $result->applied);
        self::assertSame([], $writer->writes);
    }

    /**
     * @param list<AfasSamenstelling> $snapshot
     */
    private function buildHandler(ItemcodeParentWriter $writer, array $snapshot): FixBaseParentHandler
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);

        // Eén groep Lifepak CR2 semi (head 11161, base 11164/11155); evt. tweede groep Mindray C1 semi (head 21018, base 21011).
        $groups->save(new Group('Lifepak CR2 semi', '11161'));
        $bases->saveForGroup('11161', new GroupBase(null, 'NL/EN', 'NL/EN', '11164'));
        $bases->saveForGroup('11161', new GroupBase(null, 'NL', 'NL', '11155'));

        $hasMindray = false;
        foreach ($snapshot as $s) {
            if ($s->itemcode === '21018' || $s->itemcode === '21011') {
                $hasMindray = true;
            }
        }
        if ($hasMindray) {
            $groups->save(new Group('Mindray C1 semi', '21018'));
            $bases->saveForGroup('21018', new GroupBase(null, '3-talig', 'NL/EN/FR', '21011'));
        }

        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot($snapshot);

        return new FixBaseParentHandler(
            new BaseParentAuditHandler($groups, $bases, $afas),
            $writer,
        );
    }
}

final class RecordingItemcodeParentWriter implements ItemcodeParentWriter
{
    /** @var list<array{0: string, 1: string}> */
    public array $writes = [];

    public function write(string $itemcode, string $parent): void
    {
        $this->writes[] = [$itemcode, $parent];
    }
}

final readonly class FailingItemcodeParentWriter implements ItemcodeParentWriter
{
    public function __construct(private string $message)
    {
    }

    public function write(string $itemcode, string $parent): void
    {
        throw ItemcodeParentWriteFailedException::from($itemcode, new RuntimeException($this->message));
    }
}
