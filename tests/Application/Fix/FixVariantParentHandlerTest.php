<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\VariantParentAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixVariantParent;
use Defibrion\Samenstellingen\Application\Fix\FixVariantParentHandler;
use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriteFailedException;
use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriter;
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
use RuntimeException;

final class FixVariantParentHandlerTest extends TestCase
{
    #[Test]
    public function dryRunListsPlansAndSkippedWithoutInvokingWriter(): void
    {
        $writer = new VariantRecordingItemcodeParentWriter();
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11043', 'Head NL', '11043', []),
            new AfasSamenstelling('11043-60110', 'NL + Backpack', null, []),    // leeg → plan
            new AfasSamenstelling('11043-60112', 'NL + Wit', '99999', []),       // afwijkend → skip
            new AfasSamenstelling('11043-60122', 'NL + Groen', '11043', []),     // OK → niets
        ]);

        $result = $handler(new FixVariantParent(apply: false));

        self::assertCount(1, $result->plans);
        self::assertCount(1, $result->skippedFilled);
        self::assertSame(0, $result->applied);
        self::assertSame([], $writer->writes);
    }

    #[Test]
    public function applyInvokesWriterPerPlanOnly(): void
    {
        $writer = new VariantRecordingItemcodeParentWriter();
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11043', 'Head', '11043', []),
            new AfasSamenstelling('11043-60110', 'Backpack', null, []),
            new AfasSamenstelling('11043-60112', 'Wit', '99999', []),
        ]);

        $result = $handler(new FixVariantParent(apply: true));

        self::assertSame(1, $result->applied);
        self::assertSame([['11043-60110', '11043']], $writer->writes);
        self::assertCount(1, $result->skippedFilled);
    }

    #[Test]
    public function applyReportsWriterFailures(): void
    {
        $writer = new VariantFailingItemcodeParentWriter('AFAS gooit 500');
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11043', 'Head', '11043', []),
            new AfasSamenstelling('11043-60110', 'Backpack', null, []),
        ]);

        $result = $handler(new FixVariantParent(apply: true));

        self::assertSame(0, $result->applied);
        self::assertCount(1, $result->failures);
        self::assertSame('11043-60110', $result->failures[0]['plan']->afasItemcode);
        self::assertStringContainsString('AFAS gooit 500', $result->failures[0]['error']);
    }

    #[Test]
    public function emptyAuditResultYieldsEmptyResult(): void
    {
        $writer = new VariantRecordingItemcodeParentWriter();
        $handler = $this->buildHandler($writer, [
            new AfasSamenstelling('11043', 'Head', '11043', []),
            new AfasSamenstelling('11043-60110', 'Backpack', '11043', []), // al goed
        ]);

        $result = $handler(new FixVariantParent(apply: true));

        self::assertSame([], $result->plans);
        self::assertSame([], $result->skippedFilled);
        self::assertSame(0, $result->applied);
        self::assertSame([], $writer->writes);
    }

    /**
     * @param list<AfasSamenstelling> $snapshot
     */
    private function buildHandler(ItemcodeParentWriter $writer, array $snapshot): FixVariantParentHandler
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
        $accessoires->save(new Accessoire('60112', 'Wit'));
        $accessoires->save(new Accessoire('60122', 'Groen'));
        foreach (['60110', '60112', '60122'] as $code) {
            $links->link('11043', $code);
        }
        $variants->regenerateForGroup('11043');
        foreach ($variants->findAllForGroup('11043') as $variant) {
            self::assertNotNull($variant->id);
            if ($variant->accessoireItemcode !== null) {
                $variants->markMatched($variant->id, '11043-' . $variant->accessoireItemcode);
            }
        }

        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot($snapshot);

        return new FixVariantParentHandler(
            new VariantParentAuditHandler($groups, $bases, $variants, $afas),
            $writer,
        );
    }
}

final class VariantRecordingItemcodeParentWriter implements ItemcodeParentWriter
{
    /** @var list<array{0: string, 1: string}> */
    public array $writes = [];

    public function write(string $itemcode, string $parent): void
    {
        $this->writes[] = [$itemcode, $parent];
    }
}

final readonly class VariantFailingItemcodeParentWriter implements ItemcodeParentWriter
{
    public function __construct(private string $message)
    {
    }

    public function write(string $itemcode, string $parent): void
    {
        throw ItemcodeParentWriteFailedException::from($itemcode, new RuntimeException($this->message));
    }
}
