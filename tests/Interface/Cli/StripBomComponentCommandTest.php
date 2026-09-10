<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Bom\StripBomComponentHandler;
use Defibrion\Samenstellingen\Domain\Bom\BomLine;
use Defibrion\Samenstellingen\Infrastructure\Bom\InMemory\InMemoryBomComponentStripWriter;
use Defibrion\Samenstellingen\Infrastructure\Bom\InMemory\InMemoryBomLineReader;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\StripBomComponentCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class StripBomComponentCommandTest extends TestCase
{
    #[Test]
    public function onlyWithScopesThePlanAndReportsSkippedLines(): void
    {
        $tester = $this->buildTester();

        $tester->execute(['itemcode' => '81111', '--only-with' => '81511, 81611']);
        $output = $tester->getDisplay();

        self::assertStringContainsString('2 AFAS-regel(s)', $output);
        self::assertStringContainsString('11145-DE', $output);
        self::assertStringContainsString('11145-EN', $output);
        self::assertStringNotContainsString('11142', $output);
        self::assertStringContainsString('1 regel(s) overgeslagen', $output);
        self::assertStringContainsString('81511, 81611', $output);
    }

    #[Test]
    public function withoutOnlyWithNoSkipLineIsPrinted(): void
    {
        $tester = $this->buildTester();

        $tester->execute(['itemcode' => '81111']);
        $output = $tester->getDisplay();

        self::assertStringContainsString('3 AFAS-regel(s)', $output);
        self::assertStringNotContainsString('overgeslagen', $output);
    }

    private function buildTester(): CommandTester
    {
        $reader = (new InMemoryBomLineReader())->withLines(
            new BomLine('11145-DE', '81111', 'Sam', 30),
            new BomLine('11145-DE', '81511', 'Sam', 40),
            new BomLine('11142', '81111', 'Sam', 30),
            new BomLine('11145-EN', '81111', 'Sam', 30),
            new BomLine('11145-EN', '81611', 'Sam', 40),
        );
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $handler = new StripBomComponentHandler(
            $reader,
            new InMemoryBomComponentStripWriter(),
            new InMemoryGroupBaseItemRepository($bases),
        );

        return new CommandTester(new StripBomComponentCommand($handler));
    }
}
