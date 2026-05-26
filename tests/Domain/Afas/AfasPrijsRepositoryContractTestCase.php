<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class AfasPrijsRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): AfasPrijsRepository;

    #[Test]
    public function findByItemcodeReturnsEmptyWhenSnapshotEmpty(): void
    {
        self::assertSame([], $this->makeRepository()->findByItemcode('11142'));
    }

    #[Test]
    public function replaceSnapshotStoresAndCounts(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([
            new AfasPrijs('11142', '*****', null, 199900, null, '2025-01-01', null),
            new AfasPrijs('11142', '003', null, 169900, null, '2025-01-01', null),
            new AfasPrijs('60110', '*****', null, 7900, null, '2025-01-01', null),
        ]);

        self::assertSame(3, $repo->countSnapshot());
        self::assertCount(2, $repo->findByItemcode('11142'));
        self::assertCount(1, $repo->findByItemcode('60110'));
    }

    #[Test]
    public function replaceSnapshotIsIdempotentAndReplaces(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([new AfasPrijs('11142', '*****', null, 100, null, '2025-01-01', null)]);
        $repo->replaceSnapshot([new AfasPrijs('11142', '*****', null, 200, null, '2025-01-01', null)]);

        $found = $repo->findByItemcode('11142');
        self::assertCount(1, $found);
        self::assertSame(200, $found[0]->verkoopprijsCents);
    }

    #[Test]
    public function replaceSnapshotWithEmptyListClearsTable(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([new AfasPrijs('11142', '*****', null, 100, null, '2025-01-01', null)]);
        $repo->replaceSnapshot([]);

        self::assertSame(0, $repo->countSnapshot());
    }

    #[Test]
    public function preservesDebtorAndStaffel(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([
            new AfasPrijs('11142', '003', 'DEB001', 169900, 5, '2025-01-01', '2025-12-31'),
        ]);

        $found = $repo->findByItemcode('11142');
        self::assertCount(1, $found);
        self::assertSame('DEB001', $found[0]->debiteurId);
        self::assertSame(5, $found[0]->staffelAantal);
        self::assertSame('2025-12-31', $found[0]->geldigTot);
    }
}
