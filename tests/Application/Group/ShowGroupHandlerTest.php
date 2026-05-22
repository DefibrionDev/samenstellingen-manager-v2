<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\ShowGroup;
use Defibrion\Samenstellingen\Application\Group\ShowGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShowGroupHandlerTest extends TestCase
{
    #[Test]
    public function returnsExistingGroup(): void
    {
        $repository = new InMemoryGroupRepository();
        $repository->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $handler = new ShowGroupHandler($repository);

        $group = $handler(new ShowGroup('Reanibex 100 Semi-Auto'));

        self::assertSame('Reanibex 100 Semi-Auto', $group->name);
        self::assertSame('52112', $group->familyHeadItemcode);
    }

    #[Test]
    public function throwsWhenGroupNotFound(): void
    {
        $handler = new ShowGroupHandler(new InMemoryGroupRepository());

        $this->expectException(GroupNotFoundException::class);
        $this->expectExceptionMessage("Groep 'Onbekend' niet gevonden");

        $handler(new ShowGroup('Onbekend'));
    }
}
