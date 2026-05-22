<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddAccessoireToGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
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
        [$groups, , $accessoires, $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $tester = new CommandTester(
            new AddAccessoireCommand(new AddAccessoireToGroupHandler($links, $variants)),
        );

        $exitCode = $tester->execute([
            'family-head-itemcode' => '52112',
            'accessoire-itemcode' => '60112',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $links->findAllForGroup('52112'));
    }

    #[Test]
    public function failsForUnknownAccessoire(): void
    {
        [$groups, , , $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $tester = new CommandTester(
            new AddAccessoireCommand(new AddAccessoireToGroupHandler($links, $variants)),
        );

        $exitCode = $tester->execute([
            'family-head-itemcode' => '52112',
            'accessoire-itemcode' => '99999',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('bestaat niet in de catalogus', $tester->getDisplay());
    }

    #[Test]
    public function failsForDuplicateLink(): void
    {
        [$groups, , $accessoires, $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $tester = new CommandTester(
            new AddAccessoireCommand(new AddAccessoireToGroupHandler($links, $variants)),
        );

        $tester->execute(['family-head-itemcode' => '52112', 'accessoire-itemcode' => '60112']);
        $exitCode = $tester->execute([
            'family-head-itemcode' => '52112',
            'accessoire-itemcode' => '60112',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('al gekoppeld', $tester->getDisplay());
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryGroupBaseRepository, 2: InMemoryAccessoireRepository, 3: InMemoryGroupAccessoireRepository, 4: InMemoryGroupVariantRepository}
     */
    private function repos(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        return [$groups, $bases, $accessoires, $links, $variants];
    }
}
