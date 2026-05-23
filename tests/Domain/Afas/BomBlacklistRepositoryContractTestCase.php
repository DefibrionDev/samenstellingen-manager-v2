<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Afas;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Afas\BomCodeAlreadyBlacklistedException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class BomBlacklistRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): BomBlacklistRepository;

    #[Test]
    public function savesAndRetrievesEntry(): void
    {
        $repo = $this->makeRepository();
        $repo->save(new BomBlacklistEntry('81311', 'Waalse stickerset — geen base-taal'));

        $found = $repo->findByItemcode('81311');

        self::assertNotNull($found);
        self::assertSame('81311', $found->itemcode);
        self::assertSame('Waalse stickerset — geen base-taal', $found->reason);
    }

    #[Test]
    public function returnsNullForUnknownItemcode(): void
    {
        self::assertNull($this->makeRepository()->findByItemcode('99999'));
    }

    #[Test]
    public function rejectsDuplicateItemcode(): void
    {
        $repo = $this->makeRepository();
        $repo->save(new BomBlacklistEntry('81311', 'Waalse stickerset'));

        $this->expectException(BomCodeAlreadyBlacklistedException::class);
        $this->expectExceptionMessage("BOM-itemcode '81311' staat al op de blacklist");

        $repo->save(new BomBlacklistEntry('81311', 'andere reden'));
    }

    #[Test]
    public function findAllReturnsEmptyListWhenEmpty(): void
    {
        self::assertSame([], $this->makeRepository()->findAll());
    }

    #[Test]
    public function findAllReturnsAllEntries(): void
    {
        $repo = $this->makeRepository();
        $repo->save(new BomBlacklistEntry('81311', 'Waalse stickerset'));
        $repo->save(new BomBlacklistEntry('99999', 'Reserved'));

        $all = $repo->findAll();
        $codes = array_map(static fn (BomBlacklistEntry $e) => $e->itemcode, $all);

        self::assertCount(2, $all);
        self::assertContains('81311', $codes);
        self::assertContains('99999', $codes);
    }
}
