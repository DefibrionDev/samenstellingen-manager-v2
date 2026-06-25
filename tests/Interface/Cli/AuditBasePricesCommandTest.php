<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\BasePriceGapsHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryPrijslijstWhitelistRepository;
use Defibrion\Samenstellingen\Interface\Cli\AuditBasePricesCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuditBasePricesCommandTest extends TestCase
{
    #[Test]
    public function rendersTableForBaseMissingFromWhitelistedPrijslijst(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $prijzen = new InMemoryAfasPrijsRepository();
        $prijslijsten = new InMemoryAfasPrijslijstRepository();
        $whitelist = new InMemoryPrijslijstWhitelistRepository();

        $groups->save(new Group('Reanibex', '52112'));
        $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '11142'));
        $prijslijsten->replaceSnapshot([new AfasPrijslijst('003', 'Dealers FR')]);
        $whitelist->add('003', 'test');
        // Geen prijs in 003 → gap.
        $prijzen->replaceSnapshot([]);

        $handler = new BasePriceGapsHandler($groups, $bases, $prijzen, $prijslijsten, $whitelist);
        $tester = new CommandTester(new AuditBasePricesCommand($handler));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('003 — Dealers FR', $display);
        self::assertStringContainsString('11142', $display);
        self::assertStringContainsString('AED pakket NL', $display);
        self::assertStringContainsString('1 base(s) ontbreken', $display);
    }

    #[Test]
    public function showsPlaceholderWhenNoGaps(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $prijzen = new InMemoryAfasPrijsRepository();
        $prijslijsten = new InMemoryAfasPrijslijstRepository();
        $whitelist = new InMemoryPrijslijstWhitelistRepository();

        $groups->save(new Group('Reanibex', '52112'));
        $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '11142'));
        $prijslijsten->replaceSnapshot([new AfasPrijslijst('003', 'Dealers FR')]);
        $whitelist->add('003', 'test');
        $prijzen->replaceSnapshot([
            new AfasPrijs('11142', '003', null, 189900, null, '2025-01-01', null),
        ]);

        $handler = new BasePriceGapsHandler($groups, $bases, $prijzen, $prijslijsten, $whitelist);
        $tester = new CommandTester(new AuditBasePricesCommand($handler));

        $tester->execute([]);

        self::assertStringContainsString('Elke managed base staat in alle whitelist-prijslijsten', $tester->getDisplay());
    }
}
