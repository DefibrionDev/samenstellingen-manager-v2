<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Accessoire;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class AccessoireRepositoryContractTestCase extends TestCase
{
    abstract protected function makeRepository(): AccessoireRepository;

    #[Test]
    public function savesAndRetrievesAccessoire(): void
    {
        $repo = $this->makeRepository();
        $repo->save(new Accessoire('60112', 'ARKY witte binnenkast'));

        $found = $repo->findByItemcode('60112');

        self::assertNotNull($found);
        self::assertSame('60112', $found->itemcode);
        self::assertSame('ARKY witte binnenkast', $found->label);
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
        $repo->save(new Accessoire('60112', 'ARKY witte binnenkast'));

        $this->expectException(AccessoireAlreadyExistsException::class);
        $this->expectExceptionMessage("Accessoire met itemcode '60112' bestaat al in de catalogus");

        $repo->save(new Accessoire('60112', 'iets anders'));
    }
}
