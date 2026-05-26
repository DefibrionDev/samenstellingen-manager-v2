<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Accessoire;

use Defibrion\Samenstellingen\Application\Accessoire\SetAccessoireDelta;
use Defibrion\Samenstellingen\Application\Accessoire\SetAccessoireDeltaHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SetAccessoireDeltaHandlerTest extends TestCase
{
    #[Test]
    public function updatesDeltaOnExistingAccessoire(): void
    {
        $repo = new InMemoryAccessoireRepository();
        $repo->save(new Accessoire('60110', 'EHBO-Rugzak', 0));

        (new SetAccessoireDeltaHandler($repo))(new SetAccessoireDelta('60110', 7900));

        $found = $repo->findByItemcode('60110');
        self::assertNotNull($found);
        self::assertSame(7900, $found->deltaCents);
    }

    #[Test]
    public function throwsForUnknownAccessoire(): void
    {
        $handler = new SetAccessoireDeltaHandler(new InMemoryAccessoireRepository());

        $this->expectException(AccessoireNotFoundException::class);
        $handler(new SetAccessoireDelta('99999', 100));
    }

    #[Test]
    public function rejectsNegativeDelta(): void
    {
        $repo = new InMemoryAccessoireRepository();
        $repo->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $handler = new SetAccessoireDeltaHandler($repo);

        $this->expectException(InvalidArgumentException::class);
        $handler(new SetAccessoireDelta('60110', -100));
    }
}
