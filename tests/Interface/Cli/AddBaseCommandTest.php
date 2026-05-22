<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddBaseToGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\AddBaseCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddBaseCommandTest extends TestCase
{
    #[Test]
    public function addsBaseToExistingGroup(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $tester = new CommandTester(new AddBaseCommand(new AddBaseToGroupHandler($bases)));

        $exitCode = $tester->execute([
            'group' => 'Reanibex 100 Semi-Auto',
            'itemcode' => '50013',
            'language-code' => 'NL',
            'name' => 'Reanibex 100 Semi-Automatic AED Nederlands',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $bases->findAllForGroup('Reanibex 100 Semi-Auto'));
    }

    #[Test]
    public function failsForUnknownGroup(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $tester = new CommandTester(new AddBaseCommand(new AddBaseToGroupHandler($bases)));

        $exitCode = $tester->execute([
            'group' => 'Onbekend',
            'itemcode' => '50013',
            'language-code' => 'NL',
            'name' => 'naam',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString("Groep 'Onbekend' niet gevonden", $tester->getDisplay());
    }

    #[Test]
    public function failsForDuplicateBase(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $tester = new CommandTester(new AddBaseCommand(new AddBaseToGroupHandler($bases)));

        $tester->execute([
            'group' => 'Reanibex 100 Semi-Auto',
            'itemcode' => '50013',
            'language-code' => 'NL',
            'name' => 'naam',
        ]);
        $exitCode = $tester->execute([
            'group' => 'Reanibex 100 Semi-Auto',
            'itemcode' => '50013',
            'language-code' => 'DE',
            'name' => 'andere',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('bestaat al', $tester->getDisplay());
    }
}
