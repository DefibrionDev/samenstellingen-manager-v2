<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\ListGroupsCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ListGroupsCommandTest extends TestCase
{
    #[Test]
    public function rendersTableWithFamilyHeadNameAndBaseCount(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $groups->save(new Group('Heartsine 350P', '10013', 'Heartsine Samaritan PAD 350P'));
        $groups->save(new Group('Reanibex 100', '52112'));
        $bases->saveForGroup('10013', new GroupBase(null, 'AED NL', 'NL', '11111'));
        $bases->saveForGroup('10013', new GroupBase(null, 'AED FR', 'FR', '11112'));

        $tester = new CommandTester(new ListGroupsCommand($groups, $bases));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('10013', $display);
        self::assertStringContainsString('Heartsine 350P', $display);
        self::assertStringContainsString('Heartsine Samaritan PAD 350P', $display);
        self::assertStringContainsString('52112', $display);
        self::assertStringContainsString('Reanibex 100', $display);
        self::assertStringContainsString('2 groep(en)', $display);
    }

    #[Test]
    public function showsPlaceholderWhenNoGroupsRegistered(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);

        $tester = new CommandTester(new ListGroupsCommand($groups, $bases));
        $tester->execute([]);

        self::assertStringContainsString('Geen groepen geregistreerd', $tester->getDisplay());
    }

    #[Test]
    public function writesCsvToGivenPathWhenOptionProvided(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $groups->save(new Group('Heartsine 350P', '10013', 'Heartsine Samaritan PAD 350P'));
        $groups->save(new Group('Reanibex 100', '52112'));
        $bases->saveForGroup('10013', new GroupBase(null, 'AED NL', 'NL', '11111'));

        $path = tempnam(sys_get_temp_dir(), 'group-list-csv-');
        self::assertNotFalse($path);

        try {
            $tester = new CommandTester(new ListGroupsCommand($groups, $bases));
            $tester->execute(['--csv' => $path]);

            $content = file_get_contents($path);
            self::assertIsString($content);
            self::assertStringContainsString('family_head,name,model_name_nl,bases_count', $content);
            self::assertStringContainsString('10013,"Heartsine 350P","Heartsine Samaritan PAD 350P",1', $content);
            self::assertStringContainsString('52112,"Reanibex 100",,0', $content);
            self::assertStringContainsString('geëxporteerd naar', $tester->getDisplay());
        } finally {
            @unlink($path);
        }
    }
}
