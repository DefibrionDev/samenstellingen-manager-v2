<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\CreateGroup;
use Defibrion\Samenstellingen\Application\Group\CreateGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateGroupHandlerTest extends TestCase
{
    #[Test]
    public function persistsAndReturnsGroup(): void
    {
        $repository = new InMemoryGroupRepository();
        $handler = new CreateGroupHandler($repository);

        $group = $handler(new CreateGroup('Reanibex 100 Semi-Auto', '52112'));

        self::assertSame('Reanibex 100 Semi-Auto', $group->name);
        self::assertSame('52112', $group->familyHeadItemcode);

        $found = $repository->findByName('Reanibex 100 Semi-Auto');
        self::assertNotNull($found);
        self::assertSame('52112', $found->familyHeadItemcode);
    }

    #[Test]
    public function passesThroughDuplicateException(): void
    {
        $repository = new InMemoryGroupRepository();
        $handler = new CreateGroupHandler($repository);
        $handler(new CreateGroup('Reanibex 100 Semi-Auto', '52112'));

        $this->expectException(GroupAlreadyExistsException::class);
        $this->expectExceptionMessage("Groep 'Reanibex 100 Semi-Auto' bestaat al");

        $handler(new CreateGroup('Reanibex 100 Semi-Auto', '52199'));
    }
}
