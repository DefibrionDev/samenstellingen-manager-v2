<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\AddAccessoireCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddAccessoireCommandTest extends TestCase
{
    #[Test]
    public function linksAccessoireToGroup(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $tester = new CommandTester(new AddAccessoireCommand(new AddAccessoireToGroupHandler($links)));

        $exitCode = $tester->execute([
            'group' => 'Reanibex 100 Semi-Auto',
            'accessoire-itemcode' => '60112',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $links->findAllForGroup('Reanibex 100 Semi-Auto'));
    }

    #[Test]
    public function failsForUnknownAccessoire(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $tester = new CommandTester(new AddAccessoireCommand(new AddAccessoireToGroupHandler($links)));

        $exitCode = $tester->execute([
            'group' => 'Reanibex 100 Semi-Auto',
            'accessoire-itemcode' => '99999',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('bestaat niet in de catalogus', $tester->getDisplay());
    }

    #[Test]
    public function failsForDuplicateLink(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $tester = new CommandTester(new AddAccessoireCommand(new AddAccessoireToGroupHandler($links)));

        $tester->execute(['group' => 'Reanibex 100 Semi-Auto', 'accessoire-itemcode' => '60112']);
        $exitCode = $tester->execute([
            'group' => 'Reanibex 100 Semi-Auto',
            'accessoire-itemcode' => '60112',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('al gekoppeld', $tester->getDisplay());
    }
}
