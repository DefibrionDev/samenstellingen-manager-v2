<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\CreateGroupHandler;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\CreateGroupCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateGroupCommandTest extends TestCase
{
    #[Test]
    public function createsGroupAndReturnsSuccess(): void
    {
        $repository = new InMemoryGroupRepository();
        $tester = new CommandTester(new CreateGroupCommand(new CreateGroupHandler($repository)));

        $exitCode = $tester->execute([
            'name' => 'Reanibex 100 Semi-Auto',
            'family-head-itemcode' => '52112',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Reanibex 100 Semi-Auto', $tester->getDisplay());
        self::assertStringContainsString('52112', $tester->getDisplay());

        $persisted = $repository->findByName('Reanibex 100 Semi-Auto');
        self::assertNotNull($persisted);
        self::assertSame('52112', $persisted->familyHeadItemcode);
    }

    #[Test]
    public function failsWithMessageOnDuplicate(): void
    {
        $repository = new InMemoryGroupRepository();
        $tester = new CommandTester(new CreateGroupCommand(new CreateGroupHandler($repository)));

        $tester->execute(['name' => 'Reanibex 100 Semi-Auto', 'family-head-itemcode' => '52112']);
        $exitCode = $tester->execute([
            'name' => 'Reanibex 100 Semi-Auto',
            'family-head-itemcode' => '52199',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString("Groep 'Reanibex 100 Semi-Auto' bestaat al", $tester->getDisplay());
    }

    #[Test]
    public function failsWithInvalidExitCodeOnBlankName(): void
    {
        $repository = new InMemoryGroupRepository();
        $tester = new CommandTester(new CreateGroupCommand(new CreateGroupHandler($repository)));

        $exitCode = $tester->execute([
            'name' => '   ',
            'family-head-itemcode' => '52112',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Groepsnaam mag niet leeg zijn', $tester->getDisplay());
    }
}
