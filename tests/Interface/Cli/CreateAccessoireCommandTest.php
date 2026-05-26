<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Accessoire\CreateAccessoireHandler;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Interface\Cli\CreateAccessoireCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAccessoireCommandTest extends TestCase
{
    #[Test]
    public function createsAccessoire(): void
    {
        $repository = new InMemoryAccessoireRepository();
        $tester = new CommandTester(new CreateAccessoireCommand(new CreateAccessoireHandler($repository)));

        $exitCode = $tester->execute([
            'itemcode' => '60112',
            'label' => 'ARKY witte binnenkast',
            'delta-eur' => '295',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $found = $repository->findByItemcode('60112');
        self::assertNotNull($found);
        self::assertSame(29500, $found->deltaCents);
    }

    #[Test]
    public function failsOnDuplicate(): void
    {
        $repository = new InMemoryAccessoireRepository();
        $tester = new CommandTester(new CreateAccessoireCommand(new CreateAccessoireHandler($repository)));

        $tester->execute(['itemcode' => '60112', 'label' => 'ARKY witte binnenkast', 'delta-eur' => '295']);
        $exitCode = $tester->execute(['itemcode' => '60112', 'label' => 'iets anders', 'delta-eur' => '100']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('bestaat al in de catalogus', $tester->getDisplay());
    }

    #[Test]
    public function rejectsInvalidDelta(): void
    {
        $tester = new CommandTester(new CreateAccessoireCommand(new CreateAccessoireHandler(new InMemoryAccessoireRepository())));

        $exitCode = $tester->execute([
            'itemcode' => '60112',
            'label' => 'X',
            'delta-eur' => 'abc',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
    }
}
