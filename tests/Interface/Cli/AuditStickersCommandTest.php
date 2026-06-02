<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Cli;

use Defibrion\Samenstellingen\Application\Audit\StickerAuditHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Interface\Cli\AuditStickersCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class AuditStickersCommandTest extends TestCase
{
    #[Test]
    public function showsRestoreBannerWhenAllDriftSharesOneExpectedSticker(): void
    {
        $tester = $this->buildTester([
            ['11142-EN', 'EN', ['10142-EN', '70112']], // verwacht 81611, mist
            ['11999', 'EN', ['10999', '70112']],       // verwacht 81611, mist
        ]);

        $tester->execute([]);
        $output = $tester->getDisplay();

        self::assertStringContainsString('stickers:restore', $output);
        self::assertStringContainsString('81611', $output);
    }

    #[Test]
    public function suppressesBannerWhenMultipleExpectedStickersInDrift(): void
    {
        $tester = $this->buildTester([
            ['11142-EN', 'EN', ['10142-EN', '70112']], // verwacht 81611
            ['11142', 'NL', ['10142', '70112']],       // verwacht 81111
        ]);

        $tester->execute([]);

        self::assertStringNotContainsString('stickers:restore', $tester->getDisplay());
    }

    /**
     * @param list<array{0: string, 1: string, 2: list<string>}> $bases  [afas_itemcode, language, bom]
     */
    private function buildTester(array $bases): CommandTester
    {
        $groups = new InMemoryGroupRepository();
        $baseRepo = new InMemoryGroupBaseRepository($groups);
        $afas = new InMemoryAfasSamenstellingenRepository();

        $samenstellingen = [];
        foreach ($bases as $i => [$afasItemcode, $language, $bom]) {
            $familyHead = $afasItemcode . '-fh' . $i;
            $groups->save(new Group('Groep ' . $i, $familyHead));
            $baseRepo->saveForGroup($familyHead, new GroupBase(null, 'Base ' . $i, $language, $afasItemcode));
            $samenstellingen[] = new AfasSamenstelling($afasItemcode, 'Naam ' . $i, null, $bom);
        }
        $afas->replaceSnapshot($samenstellingen);

        $handler = new StickerAuditHandler($groups, $baseRepo, $afas);

        return new CommandTester(new AuditStickersCommand($handler));
    }
}
