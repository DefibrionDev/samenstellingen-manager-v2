<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class AfasPrijslijstRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): AfasPrijslijstRepository;

    #[Test]
    public function emptyByDefault(): void
    {
        $repo = $this->makeRepository();
        self::assertSame([], $repo->findAll());
        self::assertSame(0, $repo->countSnapshot());
        self::assertNull($repo->findById('010'));
    }

    #[Test]
    public function replaceSnapshotStoresAndLooksUp(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([
            new AfasPrijslijst('010', 'Farys'),
            new AfasPrijslijst('011', 'IOK'),
            new AfasPrijslijst('*****', 'Basisprijslijst (excl BTW)'),
        ]);

        self::assertSame(3, $repo->countSnapshot());
        $farys = $repo->findById('010');
        self::assertNotNull($farys);
        self::assertSame('Farys', $farys->omschrijving);
        self::assertSame('Basisprijslijst (excl BTW)', $repo->findById('*****')?->omschrijving);
    }

    #[Test]
    public function findAllReturnsSnapshotSortedById(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([
            new AfasPrijslijst('027', 'Dealers Benelux'),
            new AfasPrijslijst('003', 'Dealers FR'),
            new AfasPrijslijst('010', 'Farys'),
        ]);

        $all = $repo->findAll();
        self::assertSame(['003', '010', '027'], array_map(fn ($p) => $p->id, $all));
    }

    #[Test]
    public function replaceSnapshotIsIdempotentAndReplaces(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([new AfasPrijslijst('010', 'Farys')]);
        $repo->replaceSnapshot([new AfasPrijslijst('010', 'Farys (nieuwe naam)')]);

        self::assertSame(1, $repo->countSnapshot());
        self::assertSame('Farys (nieuwe naam)', $repo->findById('010')?->omschrijving);
    }

    #[Test]
    public function replaceSnapshotWithEmptyListClearsTable(): void
    {
        $repo = $this->makeRepository();
        $repo->replaceSnapshot([new AfasPrijslijst('010', 'Farys')]);
        $repo->replaceSnapshot([]);

        self::assertSame(0, $repo->countSnapshot());
        self::assertNull($repo->findById('010'));
    }
}
