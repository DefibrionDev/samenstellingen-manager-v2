<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use Defibrion\Samenstellingen\Interface\Cli\ShowGroupCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowGroupCommandTest extends TestCase
{
    #[Test]
    public function rendersGroupWithFullMatrix(): void
    {
        [$groups, $bases, $accessoires, $links, $variants] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases->saveForGroup(
            'Reanibex 100 Semi-Auto',
            new GroupBase('50013', 'NL', 'Reanibex 100 Semi-Automatic AED Nederlands'),
        );
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links->link('Reanibex 100 Semi-Auto', '60112');
        $variants->regenerateForGroup('Reanibex 100 Semi-Auto');

        $tester = new CommandTester(
            new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $links, $variants)),
        );
        $exitCode = $tester->execute(['name' => 'Reanibex 100 Semi-Auto']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Reanibex 100 Semi-Auto', $display);
        self::assertStringContainsString('52112', $display);
        self::assertStringContainsString('50013', $display);
        self::assertStringContainsString('60112', $display);
        self::assertStringContainsString('Varianten', $display);
        self::assertStringContainsString('(nog niet bekend)', $display);
    }

    #[Test]
    public function rendersEmptyPlaceholdersWhenNothingAdded(): void
    {
        [$groups, $bases, , $links, $variants] = $this->repositories();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $tester = new CommandTester(
            new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $links, $variants)),
        );

        $exitCode = $tester->execute(['name' => 'Reanibex 100 Semi-Auto']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('(geen bases)', $display);
        self::assertStringContainsString('(geen accessoires)', $display);
        self::assertStringContainsString('(nog geen varianten)', $display);
    }

    #[Test]
    public function failsWhenGroupNotFound(): void
    {
        [$groups, $bases, , $links, $variants] = $this->repositories();
        $tester = new CommandTester(
            new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $links, $variants)),
        );

        $exitCode = $tester->execute(['name' => 'Onbekend']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString("Groep 'Onbekend' niet gevonden", $tester->getDisplay());
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryGroupBaseRepository, 2: InMemoryAccessoireRepository, 3: InMemoryGroupAccessoireRepository, 4: InMemoryGroupVariantRepository}
     */
    private function repositories(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        return [$groups, $bases, $accessoires, $links, $variants];
    }
}
