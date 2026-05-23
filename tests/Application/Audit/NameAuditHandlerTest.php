<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditNames;
use Defibrion\Samenstellingen\Application\Audit\NameAuditHandler;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NameAuditHandlerTest extends TestCase
{
    #[Test]
    public function returnsEmptyWhenAllNamesMatchTemplate(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Reanibex', '52112', 'Reanibex 100 semi-automaat'));
        $base = $bag['bases']->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($base->id);
        $bag['variants']->regenerateForGroup('52112');

        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling(
                '52112',
                'AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
                null,
                ['50013'],
            ),
        ]);

        // Markeer variant als matched zodat audit hem oppikt.
        foreach ($bag['variants']->findAllForGroup('52112') as $v) {
            if ($v->id !== null && $v->accessoireItemcode === null) {
                $bag['variants']->markMatched($v->id, '52112');
            }
        }

        $drift = ($this->handler($bag))(new AuditNames());

        self::assertSame([], $drift);
    }

    #[Test]
    public function reportsDriftWhenAfasNameDiffersFromTemplate(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Reanibex', '52112', 'Reanibex 100 semi-automaat'));
        $base = $bag['bases']->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($base->id);
        $bag['variants']->regenerateForGroup('52112');

        $bag['afas']->replaceSnapshot([
            // Casing-drift in prefix: 'Pakket' i.p.v. 'pakket'.
            new AfasSamenstelling(
                '52112',
                'AED Pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
                null,
                ['50013'],
            ),
        ]);

        foreach ($bag['variants']->findAllForGroup('52112') as $v) {
            if ($v->id !== null && $v->accessoireItemcode === null) {
                $bag['variants']->markMatched($v->id, '52112');
            }
        }

        $drift = ($this->handler($bag))(new AuditNames());

        self::assertCount(1, $drift);
        self::assertSame('52112', $drift[0]->afasItemcode);
        self::assertSame('AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset', $drift[0]->expected);
        self::assertSame('AED Pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset', $drift[0]->actual);
        self::assertNull($drift[0]->accessoireItemcode);
    }

    #[Test]
    public function reportsDriftForVariantWithAccessoire(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Reanibex', '52112', 'Reanibex 100 semi-automaat'));
        $base = $bag['bases']->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($base->id);
        $bag['accessoires']->save(new Accessoire('60112', 'ARKY metalen binnenkast wit met alarm'));
        $bag['links']->link('52112', '60112');
        $bag['variants']->regenerateForGroup('52112');

        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling(
                '52112-60112',
                // Drift: oude "incl. safeset en stickerset"-staart i.p.v. accessoire-label.
                'AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
                '52112',
                ['50013', '60112'],
            ),
        ]);

        foreach ($bag['variants']->findAllForGroup('52112') as $v) {
            if ($v->id !== null && $v->accessoireItemcode === '60112') {
                $bag['variants']->markMatched($v->id, '52112-60112');
            }
        }

        $drift = ($this->handler($bag))(new AuditNames());

        self::assertCount(1, $drift);
        self::assertSame('60112', $drift[0]->accessoireItemcode);
        self::assertSame(
            'AED pakket: Reanibex 100 semi-automaat NL incl. ARKY metalen binnenkast wit met alarm',
            $drift[0]->expected,
        );
    }

    #[Test]
    public function skipsGroupsWithoutModelName(): void
    {
        $bag = $this->bag();
        $bag['groups']->save(new Group('Reanibex', '52112')); // geen model_name
        $base = $bag['bases']->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($base->id);
        $bag['variants']->regenerateForGroup('52112');
        $bag['afas']->replaceSnapshot([
            new AfasSamenstelling('52112', 'Wat dan ook', null, ['50013']),
        ]);
        foreach ($bag['variants']->findAllForGroup('52112') as $v) {
            if ($v->id !== null && $v->accessoireItemcode === null) {
                $bag['variants']->markMatched($v->id, '52112');
            }
        }

        $drift = ($this->handler($bag))(new AuditNames());

        self::assertSame([], $drift);
    }

    /**
     * @return array{
     *     groups: InMemoryGroupRepository,
     *     bases: InMemoryGroupBaseRepository,
     *     accessoires: InMemoryAccessoireRepository,
     *     links: InMemoryGroupAccessoireRepository,
     *     variants: InMemoryGroupVariantRepository,
     *     afas: InMemoryAfasSamenstellingenRepository
     * }
     */
    private function bag(): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        return compact('groups', 'bases', 'accessoires', 'links', 'variants', 'afas');
    }

    /**
     * @param array<string, mixed> $bag
     */
    private function handler(array $bag): NameAuditHandler
    {
        return new NameAuditHandler(
            $bag['groups'],
            $bag['bases'],
            $bag['variants'],
            $bag['accessoires'],
            $bag['afas'],
            new VariantNamingPolicy(),
        );
    }
}
