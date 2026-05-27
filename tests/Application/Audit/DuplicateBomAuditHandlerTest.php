<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditDuplicateBoms;
use Defibrion\Samenstellingen\Application\Audit\DuplicateBomAuditHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DuplicateBomAuditHandlerTest extends TestCase
{
    #[Test]
    public function emptyWhenNoSamenstellingen(): void
    {
        $handler = $this->handler([]);
        self::assertSame([], $handler(new AuditDuplicateBoms()));
    }

    #[Test]
    public function noGroupsWhenAllUnique(): void
    {
        $handler = $this->handler([
            new AfasSamenstelling('11000', 'A NL', null, ['10000', '70112', '81111']),
            new AfasSamenstelling('11001', 'B NL', null, ['10001', '70112', '81111']),
        ]);
        self::assertSame([], $handler(new AuditDuplicateBoms()));
    }

    #[Test]
    public function detectsTwoWithIdenticalBom(): void
    {
        $handler = $this->handler([
            new AfasSamenstelling('11000', 'A NL', null, ['10000', '70112', '81111']),
            new AfasSamenstelling('11000-60110', 'A NL + Rugzak', null, ['10000', '70112', '81111']),
            new AfasSamenstelling('11001', 'B NL', null, ['10001', '70112']),
        ]);
        $groups = $handler(new AuditDuplicateBoms());

        self::assertCount(1, $groups);
        self::assertSame('10000,70112,81111', $groups[0]->fingerprint);
        self::assertCount(2, $groups[0]->members);
        $itemcodes = array_column($groups[0]->members, 'itemcode');
        self::assertContains('11000', $itemcodes);
        self::assertContains('11000-60110', $itemcodes);
    }

    #[Test]
    public function bomOrderDoesNotMatter(): void
    {
        // AfasSamenstelling::__construct sorteert al — dus volgorde in input maakt niet uit
        $handler = $this->handler([
            new AfasSamenstelling('11000', 'A NL', null, ['81111', '70112', '10000']),
            new AfasSamenstelling('11000-60110', 'A NL + Rugzak', null, ['10000', '70112', '81111']),
        ]);
        $groups = $handler(new AuditDuplicateBoms());
        self::assertCount(1, $groups);
        self::assertCount(2, $groups[0]->members);
    }

    #[Test]
    public function skipsEmptyBoms(): void
    {
        // Twee samenstellingen zonder BOM zijn niet "echte" duplicaten — skippen.
        $handler = $this->handler([
            new AfasSamenstelling('11000', 'A', null, []),
            new AfasSamenstelling('11001', 'B', null, []),
        ]);
        self::assertSame([], $handler(new AuditDuplicateBoms()));
    }

    /**
     * @param list<AfasSamenstelling> $samenstellingen
     */
    private function handler(array $samenstellingen): DuplicateBomAuditHandler
    {
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot($samenstellingen);

        return new DuplicateBomAuditHandler($repo);
    }
}
