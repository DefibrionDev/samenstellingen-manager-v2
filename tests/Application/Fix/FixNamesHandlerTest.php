<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\NameAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixNames;
use Defibrion\Samenstellingen\Application\Fix\FixNamesHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryNameFixWriter;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixNamesHandlerTest extends TestCase
{
    #[Test]
    public function dryRunCollectsPlansButDoesNotWrite(): void
    {
        $bag = $this->wiringWithDrift();

        $result = ($bag['handler'])(new FixNames(apply: false));

        self::assertCount(1, $result->plans);
        self::assertSame(0, $result->appliedCount);
        self::assertSame([], $bag['writer']->applied);
    }

    #[Test]
    public function applyWritesAllPlans(): void
    {
        $bag = $this->wiringWithDrift();

        $result = ($bag['handler'])(new FixNames(apply: true));

        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $bag['writer']->applied);
        self::assertSame('52112', $bag['writer']->applied[0]->afasItemcode);
        self::assertStringContainsString('AED Pakket', $bag['writer']->applied[0]->targetName);
    }

    #[Test]
    public function failureDoesNotBlockOthers(): void
    {
        $bag = $this->wiringWithTwoDrifts(failOn: '52112');

        $result = ($bag['handler'])(new FixNames(apply: true));

        self::assertSame(1, $result->appliedCount);
        self::assertCount(1, $result->failures);
        self::assertSame('52112', $result->failures[0]['plan']->afasItemcode);
    }

    /**
     * @return array{handler: FixNamesHandler, writer: InMemoryNameFixWriter}
     */
    private function wiringWithDrift(): array
    {
        return $this->makeWiring(['52112'], failOn: null);
    }

    /**
     * @return array{handler: FixNamesHandler, writer: InMemoryNameFixWriter}
     */
    private function wiringWithTwoDrifts(?string $failOn = null): array
    {
        return $this->makeWiring(['52112', '52113'], failOn: $failOn);
    }

    /**
     * @param list<string> $afasItemcodes
     * @return array{handler: FixNamesHandler, writer: InMemoryNameFixWriter}
     */
    private function makeWiring(array $afasItemcodes, ?string $failOn): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $afas = new InMemoryAfasSamenstellingenRepository();

        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112', 'Reanibex 100 semi-automaat'));
        $afasRows = [];
        foreach ($afasItemcodes as $code) {
            $base = $bases->saveForGroup('52112', new GroupBase(null, "Naam $code", 'NL', $code));
            $variants->regenerateForGroup('52112');
            foreach ($variants->findAllForGroup('52112') as $v) {
                if ($v->id !== null && $v->baseId === $base->id) {
                    $variants->markMatched($v->id, $code);
                }
            }
            $afasRows[] = new AfasSamenstelling($code, "Foute oude naam $code", '52112', ['50013', '70112', '81111']);
        }
        $afas->replaceSnapshot($afasRows);

        $audit = new NameAuditHandler($groups, $bases, $variants, $accessoires, $afas, new VariantNamingPolicy());
        $writer = new InMemoryNameFixWriter($failOn);
        $handler = new FixNamesHandler($audit, $writer);

        return ['handler' => $handler, 'writer' => $writer];
    }
}
