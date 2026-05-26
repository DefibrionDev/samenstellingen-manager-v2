<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\AddBaseFromAfas;
use Defibrion\Samenstellingen\Application\Group\AddBaseFromAfasHandler;
use Defibrion\Samenstellingen\Application\Group\AfasSamenstellingNotInSnapshotException;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddBaseFromAfasHandlerTest extends TestCase
{
    #[Test]
    public function attachesAfasSamenstellingAsBaseIncludingBom(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Zoll AED Plus volautomaat', '11683'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling(
                '11650',
                'Zoll AED Plus Automatique FR+ ARKY safeset',
                null,
                ['10299', '10650', '70112', '81211'],
            ),
        ]);

        $base = $this->handler($bag)(new AddBaseFromAfas('11683', '11650', 'FR'));

        self::assertNotNull($base->id);
        self::assertSame('Zoll AED Plus Automatique FR+ ARKY safeset', $base->name);
        self::assertSame('FR', $base->languageCode);
        self::assertSame('11650', $base->afasItemcode);

        // BOM-items zijn ook toegevoegd
        $items = $bag['items']->findAllForBase($base->id);
        self::assertCount(4, $items);
        $codes = array_map(static fn ($item) => $item->itemcode, $items);
        self::assertContains('10299', $codes);
        self::assertContains('10650', $codes);

        // 1 base × geen accessoires gekoppeld → 1 base-only variant
        self::assertCount(1, $bag['variants']->findAllForGroup('11683'));
    }

    #[Test]
    public function throwsWhenGroupNotFound(): void
    {
        $bag = $this->bag();
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11650', 'X', null, ['10650', '70112']),
        ]);

        $this->expectException(GroupNotFoundException::class);
        $this->handler($bag)(new AddBaseFromAfas('99999', '11650', 'FR'));
    }

    #[Test]
    public function throwsWhenAfasSamenstellingNotInSnapshot(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Zoll', '11683'));

        $this->expectException(AfasSamenstellingNotInSnapshotException::class);
        $this->expectExceptionMessageMatches('/afas:pull/');
        $this->handler($bag)(new AddBaseFromAfas('11683', '99999', 'FR'));
    }

    #[Test]
    public function throwsWhenBaseWithSameNameAlreadyExists(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Zoll', '11683'));
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('11650', 'Bestaande naam', null, ['10650', '70112']),
        ]);
        // Doe dezelfde call twee keer
        $this->handler($bag)(new AddBaseFromAfas('11683', '11650', 'FR'));

        $this->expectException(BaseAlreadyExistsException::class);
        $this->handler($bag)(new AddBaseFromAfas('11683', '11650', 'FR'));
    }

    /**
     * @return array{
     *     groups: InMemoryGroupRepository,
     *     bases: InMemoryGroupBaseRepository,
     *     items: InMemoryGroupBaseItemRepository,
     *     variants: InMemoryGroupVariantRepository,
     *     afas: InMemoryAfasSamenstellingenRepository
     * }
     */
    private function bag(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $items = new InMemoryGroupBaseItemRepository($bases);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        return compact('groups', 'bases', 'items', 'variants', 'afas');
    }

    /**
     * @param array<string, mixed> $bag
     */
    private function handler(array $bag): AddBaseFromAfasHandler
    {
        return new AddBaseFromAfasHandler(
            $bag['groups'],
            $bag['bases'],
            $bag['items'],
            $bag['variants'],
            $bag['afas'],
        );
    }
}
