<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Group;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class GroupRepositoryContractTestCase extends TestCase
{
    abstract protected function repository(): GroupRepository;

    #[Test]
    public function savesAndRetrievesByName(): void
    {
        $repo = $this->repository();
        $repo->save(new Group('Reanibex 100 Semi-Auto', '52112'));

        $found = $repo->findByName('Reanibex 100 Semi-Auto');

        self::assertNotNull($found);
        self::assertSame('52112', $found->familyHeadItemcode);
    }

    #[Test]
    public function findsByFamilyHeadItemcode(): void
    {
        $repo = $this->repository();
        $repo->save(new Group('Reanibex 100 Semi-Auto', '52112'));

        $found = $repo->findByFamilyHeadItemcode('52112');

        self::assertNotNull($found);
        self::assertSame('Reanibex 100 Semi-Auto', $found->name);
    }

    #[Test]
    public function returnsNullForUnknownName(): void
    {
        self::assertNull($this->repository()->findByName('Niet bestaand'));
    }

    #[Test]
    public function returnsNullForUnknownFamilyHead(): void
    {
        self::assertNull($this->repository()->findByFamilyHeadItemcode('99999'));
    }

    #[Test]
    public function rejectsDuplicateName(): void
    {
        $repo = $this->repository();
        $repo->save(new Group('Reanibex 100 Semi-Auto', '52112'));

        $this->expectException(GroupAlreadyExistsException::class);
        $this->expectExceptionMessage("Groep 'Reanibex 100 Semi-Auto' bestaat al");

        $repo->save(new Group('Reanibex 100 Semi-Auto', '52199'));
    }

    #[Test]
    public function rejectsDuplicateFamilyHeadItemcode(): void
    {
        $repo = $this->repository();
        $repo->save(new Group('Reanibex 100 Semi-Auto', '52112'));

        $this->expectException(GroupAlreadyExistsException::class);
        $this->expectExceptionMessage("family-head itemcode '52112' bestaat al");

        $repo->save(new Group('Andere groep', '52112'));
    }

    #[Test]
    public function deleteRemovesGroup(): void
    {
        $repo = $this->repository();
        $repo->save(new Group('Reanibex', '52112'));

        $repo->delete('52112');

        self::assertNull($repo->findByFamilyHeadItemcode('52112'));
    }

    #[Test]
    public function deleteIsIdempotentForUnknownFamilyHead(): void
    {
        $repo = $this->repository();

        $repo->delete('99999');

        self::assertSame([], $repo->findAll());
    }
}
