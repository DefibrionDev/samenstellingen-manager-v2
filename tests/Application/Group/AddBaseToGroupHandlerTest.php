<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\AddBaseToGroup;
use Defibrion\Samenstellingen\Application\Group\AddBaseToGroupHandler;
use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddBaseToGroupHandlerTest extends TestCase
{
    #[Test]
    public function persistsAndReturnsBase(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $handler = new AddBaseToGroupHandler(new InMemoryGroupBaseRepository($groups));

        $base = $handler(new AddBaseToGroup(
            'Reanibex 100 Semi-Auto',
            '50013',
            'NL',
            'Reanibex 100 Semi-Automatic AED Nederlands',
        ));

        self::assertSame('50013', $base->itemcode);
        self::assertSame('NL', $base->languageCode);
    }

    #[Test]
    public function passesThroughGroupNotFound(): void
    {
        $groups = new InMemoryGroupRepository();
        $handler = new AddBaseToGroupHandler(new InMemoryGroupBaseRepository($groups));

        $this->expectException(GroupNotFoundException::class);

        $handler(new AddBaseToGroup('Onbekend', '50013', 'NL', 'naam'));
    }

    #[Test]
    public function passesThroughDuplicateBase(): void
    {
        $groups = new InMemoryGroupRepository();
        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $handler = new AddBaseToGroupHandler(new InMemoryGroupBaseRepository($groups));
        $handler(new AddBaseToGroup('Reanibex 100 Semi-Auto', '50013', 'NL', 'naam'));

        $this->expectException(BaseAlreadyExistsException::class);

        $handler(new AddBaseToGroup('Reanibex 100 Semi-Auto', '50013', 'DE', 'andere'));
    }
}
