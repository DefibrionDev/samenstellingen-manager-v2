<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstAlreadyBlacklistedException;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstNotBlacklistedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class PrijslijstBlacklistRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): PrijslijstBlacklistRepository;

    #[Test]
    public function emptyByDefault(): void
    {
        $repo = $this->makeRepository();
        self::assertSame([], $repo->findAll());
        self::assertFalse($repo->isBlacklisted('010'));
    }

    #[Test]
    public function addAndIsBlacklisted(): void
    {
        $repo = $this->makeRepository();
        $repo->add('010', 'Farys — niet alle AEDs in deze lijst');

        self::assertTrue($repo->isBlacklisted('010'));
        self::assertFalse($repo->isBlacklisted('011'));
    }

    #[Test]
    public function findAllSortedById(): void
    {
        $repo = $this->makeRepository();
        $repo->add('027', 'reden A');
        $repo->add('010', 'reden B');
        $repo->add('011', 'reden C');

        $all = $repo->findAll();
        self::assertSame(['010', '011', '027'], array_map(fn ($e) => $e->prijslijstId, $all));
        self::assertSame('reden B', $all[0]->reden);
    }

    #[Test]
    public function addingExistingThrows(): void
    {
        $repo = $this->makeRepository();
        $repo->add('010', 'oude reden');

        $this->expectException(PrijslijstAlreadyBlacklistedException::class);
        $repo->add('010', 'nieuwe reden');
    }

    #[Test]
    public function removeDeletesEntry(): void
    {
        $repo = $this->makeRepository();
        $repo->add('010', 'reden');
        $repo->remove('010');

        self::assertFalse($repo->isBlacklisted('010'));
        self::assertSame([], $repo->findAll());
    }

    #[Test]
    public function removingUnknownThrows(): void
    {
        $this->expectException(PrijslijstNotBlacklistedException::class);
        $this->makeRepository()->remove('010');
    }
}
