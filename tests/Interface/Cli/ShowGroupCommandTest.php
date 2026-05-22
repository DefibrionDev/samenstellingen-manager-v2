<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\ShowGroupCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowGroupCommandTest extends TestCase
{
    #[Test]
    public function rendersExistingGroup(): void
    {
        $repository = new InMemoryGroupRepository();
        $repository->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $tester = new CommandTester(new ShowGroupCommand(new ShowGroupHandler($repository)));

        $exitCode = $tester->execute(['name' => 'Reanibex 100 Semi-Auto']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Reanibex 100 Semi-Auto', $tester->getDisplay());
        self::assertStringContainsString('52112', $tester->getDisplay());
    }

    #[Test]
    public function failsWhenGroupNotFound(): void
    {
        $repository = new InMemoryGroupRepository();
        $tester = new CommandTester(new ShowGroupCommand(new ShowGroupHandler($repository)));

        $exitCode = $tester->execute(['name' => 'Onbekend']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString("Groep 'Onbekend' niet gevonden", $tester->getDisplay());
    }
}
