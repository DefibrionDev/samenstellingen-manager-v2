<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWebsiteRepository;
use Defibrion\Samenstellingen\Interface\Cli\AddWebsiteCommand;
use Defibrion\Samenstellingen\Interface\Cli\ListWebsitesCommand;
use Defibrion\Samenstellingen\Interface\Cli\RemoveWebsiteCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class WebsiteCommandsTest extends TestCase
{
    #[Test]
    public function addStoresWebsite(): void
    {
        $repo = new InMemoryWebsiteRepository();
        $tester = new CommandTester(new AddWebsiteCommand($repo));

        $exitCode = $tester->execute([
            'naam' => 'Reseller NL',
            'ff-sync-uuid' => 'U4E3E32DEFB374A1BA9F8680B8C405907',
            'ff-tonen-uuid' => 'UD77EC755E2F1404EB184A956685A7C0C',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($repo->findByName('Reseller NL'));
    }

    #[Test]
    public function addRejectsDuplicateName(): void
    {
        $repo = new InMemoryWebsiteRepository();
        $repo->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        $tester = new CommandTester(new AddWebsiteCommand($repo));

        $exitCode = $tester->execute([
            'naam' => 'Reseller NL',
            'ff-sync-uuid' => 'U3',
            'ff-tonen-uuid' => 'U4',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('bestaat al', $tester->getDisplay());
    }

    #[Test]
    public function listShowsRegisteredWebsites(): void
    {
        $repo = new InMemoryWebsiteRepository();
        $repo->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        $repo->save(new Website(null, 'Reseller FR', 'U3', 'U4'));
        $tester = new CommandTester(new ListWebsitesCommand($repo));

        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Reseller NL', $display);
        self::assertStringContainsString('Reseller FR', $display);
        self::assertStringContainsString('2 website(s)', $display);
    }

    #[Test]
    public function listShowsPlaceholderWhenEmpty(): void
    {
        $tester = new CommandTester(new ListWebsitesCommand(new InMemoryWebsiteRepository()));

        $tester->execute([]);

        self::assertStringContainsString('Geen websites geregistreerd', $tester->getDisplay());
    }

    #[Test]
    public function removeDeletesWebsite(): void
    {
        $repo = new InMemoryWebsiteRepository();
        $repo->save(new Website(null, 'Reseller NL', 'U1', 'U2'));
        $tester = new CommandTester(new RemoveWebsiteCommand($repo));

        $exitCode = $tester->execute(['naam' => 'Reseller NL']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNull($repo->findByName('Reseller NL'));
    }

    #[Test]
    public function removeIsIdempotentForUnknownName(): void
    {
        $tester = new CommandTester(new RemoveWebsiteCommand(new InMemoryWebsiteRepository()));

        $exitCode = $tester->execute(['naam' => 'Onbekend']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('bestaat niet', $tester->getDisplay());
    }
}
