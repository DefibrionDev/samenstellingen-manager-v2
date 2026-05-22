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
use Defibrion\Samenstellingen\Interface\Cli\ShowGroupCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowGroupCommandTest extends TestCase
{
    #[Test]
    public function rendersExistingGroupWithBasesAndAccessoires(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $bases->saveForGroup(
            'Reanibex 100 Semi-Auto',
            new GroupBase('50013', 'NL', 'Reanibex 100 Semi-Automatic AED Nederlands'),
        );
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $links->link('Reanibex 100 Semi-Auto', '60112');

        $tester = new CommandTester(new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $links)));
        $exitCode = $tester->execute(['name' => 'Reanibex 100 Semi-Auto']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Reanibex 100 Semi-Auto', $display);
        self::assertStringContainsString('52112', $display);
        self::assertStringContainsString('50013', $display);
        self::assertStringContainsString('60112', $display);
        self::assertStringContainsString('ARKY witte binnenkast', $display);
    }

    #[Test]
    public function rendersEmptyPlaceholdersWhenNoBasesOrAccessoires(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $tester = new CommandTester(new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $links)));

        $exitCode = $tester->execute(['name' => 'Reanibex 100 Semi-Auto']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('(geen bases)', $display);
        self::assertStringContainsString('(geen accessoires)', $display);
    }

    #[Test]
    public function failsWhenGroupNotFound(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $tester = new CommandTester(new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $links)));

        $exitCode = $tester->execute(['name' => 'Onbekend']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString("Groep 'Onbekend' niet gevonden", $tester->getDisplay());
    }
}
