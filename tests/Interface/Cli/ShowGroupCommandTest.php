<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use Defibrion\Samenstellingen\Interface\Cli\ShowGroupCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ShowGroupCommandTest extends TestCase
{
    #[Test]
    public function rendersGroupWithBomPerVariant(): void
    {
        [$groups, $bases, $baseItems, $accessoires, $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $base = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        self::assertNotNull($base->id);
        $baseItems->saveForBase($base->id, new GroupBaseItem('50013', 'AED Nederlands'));
        $baseItems->saveForBase($base->id, new GroupBaseItem('50015', 'Electrode'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $links->link('52112', '60112');
        $variants->regenerateForGroup('52112');

        $tester = new CommandTester(
            new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $baseItems, $links, $variants)),
        );
        $exitCode = $tester->execute(['family-head-itemcode' => '52112']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Reanibex 100 Semi-Auto', $display);
        self::assertStringContainsString('52112', $display);
        self::assertStringContainsString('50013', $display);
        self::assertStringContainsString('50015', $display);
        self::assertStringContainsString('60112', $display);
        self::assertStringContainsString('niet gecheckt', $display);
    }

    #[Test]
    public function rendersEmptyPlaceholdersWhenNothingAdded(): void
    {
        [$groups, $bases, $baseItems, , $links, $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $tester = new CommandTester(
            new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $baseItems, $links, $variants)),
        );

        $exitCode = $tester->execute(['family-head-itemcode' => '52112']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('(geen bases)', $display);
        self::assertStringContainsString('(geen accessoires)', $display);
        self::assertStringContainsString('(nog geen varianten)', $display);
    }

    #[Test]
    public function failsWhenGroupNotFound(): void
    {
        [$groups, $bases, $baseItems, , $links, $variants] = $this->repos();
        $tester = new CommandTester(
            new ShowGroupCommand(new ShowGroupHandler($groups, $bases, $baseItems, $links, $variants)),
        );

        $exitCode = $tester->execute(['family-head-itemcode' => '99999']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Geen groep gevonden', $tester->getDisplay());
    }

    /**
     * @return array{0: InMemoryGroupRepository, 1: InMemoryGroupBaseRepository, 2: InMemoryGroupBaseItemRepository, 3: InMemoryAccessoireRepository, 4: InMemoryGroupAccessoireRepository, 5: InMemoryGroupVariantRepository}
     */
    private function repos(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $baseItems = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        return [$groups, $bases, $baseItems, $accessoires, $links, $variants];
    }
}
