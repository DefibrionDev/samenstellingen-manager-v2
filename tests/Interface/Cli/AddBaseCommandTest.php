<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Group\AddBaseToGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use Defibrion\Samenstellingen\Interface\Cli\AddBaseCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AddBaseCommandTest extends TestCase
{
    #[Test]
    public function addsBaseToExistingGroup(): void
    {
        [$groups, $bases, , , $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $tester = new CommandTester(new AddBaseCommand(new AddBaseToGroupHandler($bases, $variants)));

        $exitCode = $tester->execute([
            'family-head-itemcode' => '52112',
            'name' => 'AED pakket NL',
            'language-code' => 'NL',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Base #', $tester->getDisplay());
        self::assertCount(1, $bases->findAllForGroup('52112'));
    }

    #[Test]
    public function failsForUnknownGroup(): void
    {
        [, $bases, , , $variants] = $this->repos();
        $tester = new CommandTester(new AddBaseCommand(new AddBaseToGroupHandler($bases, $variants)));

        $exitCode = $tester->execute([
            'family-head-itemcode' => '99999',
            'name' => 'naam',
            'language-code' => 'NL',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Geen groep gevonden', $tester->getDisplay());
    }

    #[Test]
    public function allowsTwoBasesWithSameNameWhenNoSku(): void
    {
        // Slice 41: naam-UNIQUE is verwijderd; itemcode is leidend. Twee bases
        // zonder SKU mogen dezelfde naam delen — een eventuele SKU-conflict
        // wordt later opgevangen door SKU-UNIQUE op (groep, afas_itemcode).
        [$groups, $bases, , , $variants] = $this->repos();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $tester = new CommandTester(new AddBaseCommand(new AddBaseToGroupHandler($bases, $variants)));

        $tester->execute(['family-head-itemcode' => '52112', 'name' => 'AED pakket NL', 'language-code' => 'NL']);
        $exitCode = $tester->execute(['family-head-itemcode' => '52112', 'name' => 'AED pakket NL', 'language-code' => 'NL']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(2, $bases->findAllForGroup('52112'));
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
