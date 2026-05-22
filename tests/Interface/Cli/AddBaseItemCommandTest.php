<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddBaseItemHandler;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\AddBaseItemCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddBaseItemCommandTest extends TestCase
{
    #[Test]
    public function addsItemToBase(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $bases = new InMemoryGroupBaseRepository($groups);
        $persisted = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL'));
        self::assertNotNull($persisted->id);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $tester = new CommandTester(new AddBaseItemCommand(new AddBaseItemHandler($items)));

        $exitCode = $tester->execute([
            'base-id' => (string) $persisted->id,
            'itemcode' => '50013',
            'name' => 'AED Nederlands',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $items->findAllForBase($persisted->id));
    }

    #[Test]
    public function failsForUnknownBase(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $tester = new CommandTester(new AddBaseItemCommand(new AddBaseItemHandler($items)));

        $exitCode = $tester->execute([
            'base-id' => '9999',
            'itemcode' => '50013',
            'name' => 'AED',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('niet gevonden', $tester->getDisplay());
    }

    #[Test]
    public function failsForInvalidBaseId(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $tester = new CommandTester(new AddBaseItemCommand(new AddBaseItemHandler($items)));

        $exitCode = $tester->execute([
            'base-id' => 'abc',
            'itemcode' => '50013',
            'name' => 'AED',
        ]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Ongeldige base-id', $tester->getDisplay());
    }
}
