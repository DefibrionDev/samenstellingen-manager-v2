<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Accessoire;

use Defibrion\Samenstellingen\Application\Accessoire\CreateAccessoire;
use Defibrion\Samenstellingen\Application\Accessoire\CreateAccessoireHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateAccessoireHandlerTest extends TestCase
{
    #[Test]
    public function persistsAndReturnsAccessoire(): void
    {
        $repository = new InMemoryAccessoireRepository();
        $handler = new CreateAccessoireHandler($repository);

        $accessoire = $handler(new CreateAccessoire('60112', 'ARKY witte binnenkast'));

        self::assertSame('60112', $accessoire->itemcode);
        self::assertSame('ARKY witte binnenkast', $accessoire->label);
        self::assertNotNull($repository->findByItemcode('60112'));
    }

    #[Test]
    public function passesThroughDuplicateException(): void
    {
        $repository = new InMemoryAccessoireRepository();
        $handler = new CreateAccessoireHandler($repository);
        $handler(new CreateAccessoire('60112', 'ARKY witte binnenkast'));

        $this->expectException(AccessoireAlreadyExistsException::class);

        $handler(new CreateAccessoire('60112', 'iets anders'));
    }
}
