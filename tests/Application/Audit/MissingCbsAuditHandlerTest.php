<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditMissingCbs;
use Defibrion\Samenstellingen\Application\Audit\MissingCbsAuditHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissingCbsAuditHandlerTest extends TestCase
{
    #[Test]
    public function returnsOnlyRowsWithMissingCbs(): void
    {
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot([
            new AfasSamenstelling('11149', 'Cardiac Science semi', '10000', [], null, null),
            new AfasSamenstelling('11148', 'Cardiac Science vol', '10000', [], null, ''),
            new AfasSamenstelling('11111', 'Heartsine 350P NL', '10013', [], null, '90189084'),
        ]);

        $handler = new MissingCbsAuditHandler($repo);

        $rows = $handler(new AuditMissingCbs());

        $itemcodes = array_map(static fn ($r) => $r->itemcode, $rows);
        sort($itemcodes);
        self::assertSame(['11148', '11149'], $itemcodes);
    }

    #[Test]
    public function returnsEmptyArrayWhenAllSamenstellingenHaveCbs(): void
    {
        $repo = new InMemoryAfasSamenstellingenRepository();
        $repo->replaceSnapshot([
            new AfasSamenstelling('11111', 'OK', '10013', [], null, '90189084'),
        ]);

        $handler = new MissingCbsAuditHandler($repo);
        $rows = $handler(new AuditMissingCbs());

        self::assertSame([], $rows);
    }
}
