<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Group;

use Defibrion\Samenstellingen\Application\Group\NormalizeBaseNames;
use Defibrion\Samenstellingen\Application\Group\NormalizeBaseNamesHandler;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Naming\VariantNamingPolicy;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NormalizeBaseNamesHandlerTest extends TestCase
{
    private InMemoryGroupRepository $groups;
    private InMemoryGroupBaseRepository $bases;
    private NormalizeBaseNamesHandler $handler;

    protected function setUp(): void
    {
        $this->groups = new InMemoryGroupRepository();
        $this->bases = new InMemoryGroupBaseRepository($this->groups);
        $this->handler = new NormalizeBaseNamesHandler($this->groups, $this->bases, new VariantNamingPolicy());
    }

    #[Test]
    public function renamesBasesToCanonicalAndSkipsAlreadyCorrect(): void
    {
        $this->groups->save(new Group('Reanibex 100 semi', '52120', 'Reanibex 100 semi-automaat', null, 'Reanibex 100 semi-automatic'));
        $this->bases->saveForGroup('52120', new GroupBase(null, 'AED Package: oude naam Zweeds', 'SE/EN/NO', '52118'));
        $this->bases->saveForGroup('52120', new GroupBase(null, 'AED Pakket: Reanibex 100 semi-automaat (NL-EN-FR)', 'NL/EN/FR', '52112'));

        $result = ($this->handler)(new NormalizeBaseNames(['52120']));

        self::assertCount(1, $result->renamed);
        self::assertSame('52118', $result->renamed[0]['afasItemcode']);
        self::assertSame('AED Package: Reanibex 100 semi-automatic (SE-EN-NO)', $result->renamed[0]['new']);

        $renamedBase = $this->bases->findByAfasItemcodeInGroup('52120', '52118');
        self::assertSame('AED Package: Reanibex 100 semi-automatic (SE-EN-NO)', $renamedBase?->name);
        $untouchedBase = $this->bases->findByAfasItemcodeInGroup('52120', '52112');
        self::assertSame('AED Pakket: Reanibex 100 semi-automaat (NL-EN-FR)', $untouchedBase?->name);
    }

    #[Test]
    public function skipsBaseWhenModelNameForBucketMissing(): void
    {
        // Groep heeft alleen NL-modelnaam; de FR-base kan niet gerenderd worden.
        $this->groups->save(new Group('Reanibex 100 semi', '52120', 'Reanibex 100 semi-automaat'));
        $this->bases->saveForGroup('52120', new GroupBase(null, 'Pack DAE: oude naam', 'FR/EN/ES', '52124'));

        $result = ($this->handler)(new NormalizeBaseNames(['52120']));

        self::assertSame([], $result->renamed);
        self::assertCount(1, $result->skipped);
        self::assertStringContainsString('52124', $result->skipped[0]);

        $base = $this->bases->findByAfasItemcodeInGroup('52120', '52124');
        self::assertSame('Pack DAE: oude naam', $base?->name);
    }

    #[Test]
    public function unknownGroupIsReportedAsSkipped(): void
    {
        $result = ($this->handler)(new NormalizeBaseNames(['99999']));

        self::assertSame([], $result->renamed);
        self::assertCount(1, $result->skipped);
        self::assertStringContainsString('99999', $result->skipped[0]);
    }
}
