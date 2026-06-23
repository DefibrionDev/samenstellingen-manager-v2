<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariantsHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use Defibrion\Samenstellingen\Interface\Cli\AuditNoMatchVariantsCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AuditNoMatchVariantsCommandTest extends TestCase
{
    #[Test]
    public function rendersTableFlaggingExistingAfasComposition(): void
    {
        // Verwachte BOM = [50013, 60110]. Bestaande compositie heeft [50013, 99999]:
        // mist 60110, en heeft 99999 teveel.
        $handler = $this->wireHandler([
            new AfasSamenstelling('11111-60110', 'Variant met Rugzak', '11111', ['50013', '99999']),
        ]);

        $tester = new CommandTester(new AuditNoMatchVariantsCommand($handler));
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Heartsine 350P', $display);
        // De verwachte itemcode bestaat al in AFAS → moet in de "bestaat in AFAS"-kolom staan.
        self::assertStringContainsString('11111-60110', $display);
        // 99999 komt alleen voor als "teveel" (zit niet in de verwachte BOM).
        self::assertStringContainsString('99999', $display);
    }

    #[Test]
    public function showsPlaceholderWhenNoNoMatchVariants(): void
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $afas = new InMemoryAfasSamenstellingenRepository();
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $groups->save(new Group('Reanibex 100', '52112'));

        $handler = new ListNoMatchVariantsHandler($groups, $variants, $items, $afas);
        $tester = new CommandTester(new AuditNoMatchVariantsCommand($handler));
        $tester->execute([]);

        self::assertStringContainsString('Geen no_match-varianten', $tester->getDisplay());
    }

    /**
     * @param list<AfasSamenstelling> $snapshot
     */
    private function wireHandler(array $snapshot): ListNoMatchVariantsHandler
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        $groups->save(new Group('Heartsine 350P', '10013'));
        $base = $bases->saveForGroup('10013', new GroupBase(null, 'AED pakket NL', 'NL', '11111'));
        self::assertNotNull($base->id);
        $items->saveForBase($base->id, new GroupBaseItem('50013', 'AED NL'));
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('10013', '60110');
        $variants->regenerateForGroup('10013');
        foreach ($variants->findAllForGroup('10013') as $variant) {
            self::assertNotNull($variant->id);
            if ($variant->accessoireItemcode === null) {
                $variants->markMatched($variant->id, '11111');
            } else {
                $variants->markNoMatch($variant->id);
            }
        }
        $afas->replaceSnapshot($snapshot);

        return new ListNoMatchVariantsHandler($groups, $variants, $items, $afas);
    }
}
