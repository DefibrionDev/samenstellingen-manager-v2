<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Website;

use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Domain\Website\WebsiteAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class WebsiteRepositoryContractTestCase extends TestCase
{
    abstract protected function repository(): WebsiteRepository;

    #[Test]
    public function savesAndAssignsId(): void
    {
        $repo = $this->repository();

        $saved = $repo->save(new Website(null, 'Reseller NL', 'U4E3', 'UD77'));

        self::assertNotNull($saved->id);
        self::assertSame('Reseller NL', $saved->name);
    }

    #[Test]
    public function findsByName(): void
    {
        $repo = $this->repository();
        $repo->save(new Website(null, 'Reseller FR', 'UAAA', 'UBBB'));

        $found = $repo->findByName('Reseller FR');
        self::assertNotNull($found);
        self::assertSame('UAAA', $found->ffSyncUuid);
        self::assertSame('UBBB', $found->ffTonenUuid);
    }

    #[Test]
    public function findsById(): void
    {
        $repo = $this->repository();
        $saved = $repo->save(new Website(null, 'Reseller DE', 'UCCC', 'UDDD'));
        self::assertNotNull($saved->id);

        $found = $repo->findById($saved->id);
        self::assertNotNull($found);
        self::assertSame('Reseller DE', $found->name);
    }

    #[Test]
    public function returnsNullForUnknownLookups(): void
    {
        $repo = $this->repository();
        self::assertNull($repo->findByName('Onbekend'));
        self::assertNull($repo->findById(9999));
    }

    #[Test]
    public function findAllReturnsRegisteredWebsites(): void
    {
        $repo = $this->repository();
        $repo->save(new Website(null, 'A', 'U1', 'U2'));
        $repo->save(new Website(null, 'B', 'U3', 'U4'));

        self::assertCount(2, $repo->findAll());
    }

    #[Test]
    public function rejectsDuplicateName(): void
    {
        $repo = $this->repository();
        $repo->save(new Website(null, 'Reseller NL', 'U1', 'U2'));

        $this->expectException(WebsiteAlreadyExistsException::class);
        $repo->save(new Website(null, 'Reseller NL', 'U3', 'U4'));
    }

    #[Test]
    public function deleteRemovesWebsite(): void
    {
        $repo = $this->repository();
        $repo->save(new Website(null, 'Reseller NL', 'U1', 'U2'));

        $repo->delete('Reseller NL');

        self::assertNull($repo->findByName('Reseller NL'));
    }

    #[Test]
    public function deleteIsIdempotentForUnknownName(): void
    {
        $repo = $this->repository();
        $repo->delete('Onbekend'); // no exception
        self::assertSame([], $repo->findAll());
    }
}
