<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditSuspiciousBases;
use Defibrion\Samenstellingen\Application\Audit\SuspiciousBaseAuditHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SuspiciousBaseAuditHandlerTest extends TestCase
{
    #[Test]
    public function flagsSamenstellingMetAccessoireSuffixZonderAccessoireInBom(): void
    {
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak YELLOW LARGE RED'));
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot([
            new AfasSamenstelling(
                '11683-60110',
                'Zoll AED Plus vol-automaat DE+ ARKY Backpack',
                '11683',
                ['10299', '10683', '70112', '81511'], // 60110 ontbreekt!
            ),
        ]);

        $rows = (new SuspiciousBaseAuditHandler($afas, $accessoires))(new AuditSuspiciousBases());

        self::assertCount(1, $rows);
        self::assertSame('11683-60110', $rows[0]->afasItemcode);
        self::assertSame('60110', $rows[0]->expectedAccessoireItemcode);
        self::assertSame('EHBO-Rugzak YELLOW LARGE RED', $rows[0]->expectedAccessoireLabel);
    }

    #[Test]
    public function geenDriftWanneerAccessoireWelInBomZit(): void
    {
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak YELLOW LARGE RED'));
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot([
            new AfasSamenstelling(
                '11683-60110',
                'Variant met Backpack',
                '11683',
                ['10683', '60110', '70112', '81511'], // 60110 zit erin → correct
            ),
        ]);

        $rows = (new SuspiciousBaseAuditHandler($afas, $accessoires))(new AuditSuspiciousBases());

        self::assertSame([], $rows);
    }

    #[Test]
    public function negeertSamenstellingZonderStreepInSku(): void
    {
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot([
            new AfasSamenstelling('52112', 'Reanibex base', null, ['50013', '70112']),
        ]);

        $rows = (new SuspiciousBaseAuditHandler($afas, $accessoires))(new AuditSuspiciousBases());

        self::assertSame([], $rows);
    }

    #[Test]
    public function negeertSkuMetTaalSuffix(): void
    {
        // 11142-FR is een base met taal-suffix, niet met accessoire-suffix.
        $accessoires = new InMemoryAccessoireRepository();
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot([
            new AfasSamenstelling('11142-FR', 'AED pakket FR', null, ['50013', '70112', '81211']),
        ]);

        $rows = (new SuspiciousBaseAuditHandler($afas, $accessoires))(new AuditSuspiciousBases());

        self::assertSame([], $rows);
    }
}
