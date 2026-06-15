<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\ProductTypeAuditHandler;
use Defibrion\Samenstellingen\Application\Fix\FixVariantProductType;
use Defibrion\Samenstellingen\Application\Fix\FixVariantProductTypeHandler;
use Defibrion\Samenstellingen\Application\Fix\ProductTypeWriteFailedException;
use Defibrion\Samenstellingen\Application\Fix\ProductTypeWriter;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixVariantProductTypeHandlerTest extends TestCase
{
    #[Test]
    public function planEmptyVariantAndWritesBaseValuesOnApply(): void
    {
        $writer = new ProductTypeRecordingWriter();
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, 'AED pakket', '350P'),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, null, null),
            writer: $writer,
        );

        $dryRun = ($handler)(new FixVariantProductType(false));
        self::assertCount(1, $dryRun->plans);
        self::assertSame(0, $dryRun->applied);
        self::assertSame([], $writer->writes);

        $applied = ($handler)(new FixVariantProductType(true));
        self::assertSame(1, $applied->applied);
        self::assertSame([['11111-60110', 'AED pakket', '350P']], $writer->writes);
    }

    #[Test]
    public function overwritesDeviantVariant(): void
    {
        $writer = new ProductTypeRecordingWriter();
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, 'AED pakket', '350P'),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, 'Toebehoren', '999'),
            writer: $writer,
        );

        ($handler)(new FixVariantProductType(true));

        self::assertSame([['11111-60110', 'AED pakket', '350P']], $writer->writes);
    }

    #[Test]
    public function skipsBlockedAndBaseEmptyWithoutWriting(): void
    {
        $writer = new ProductTypeRecordingWriter();
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, null, null),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, 'Toebehoren', '999'),
            writer: $writer,
        );

        $result = ($handler)(new FixVariantProductType(true));

        self::assertSame([], $result->plans);
        self::assertCount(2, $result->skipped); // base-leeg + geblokkeerde variant
        self::assertSame([], $writer->writes);
    }

    #[Test]
    public function recordsFailureWhenWriterThrows(): void
    {
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, 'AED pakket', '350P'),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, null, null),
            writer: new ProductTypeFailingWriter(),
        );

        $result = ($handler)(new FixVariantProductType(true));

        self::assertSame(0, $result->applied);
        self::assertCount(1, $result->failures);
        self::assertSame('11111-60110', $result->failures[0]['plan']->afasItemcode);
    }

    private function scaffold(
        AfasSamenstelling $base,
        AfasSamenstelling $variant,
        ProductTypeWriter $writer,
    ): FixVariantProductTypeHandler {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);

        $groups->save(new Group('Heartsine 350P', '11111'));
        $persistedBase = $bases->saveForGroup('11111', new GroupBase(null, 'NL', 'NL', '11111'));
        self::assertNotNull($persistedBase->id);
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('11111', '60110');
        $variants->regenerateForGroup('11111');
        foreach ($variants->findAllForGroup('11111') as $v) {
            self::assertNotNull($v->id);
            if ($v->accessoireItemcode === null) {
                $variants->markMatched($v->id, '11111');
            } elseif ($v->accessoireItemcode === '60110') {
                $variants->markMatched($v->id, '11111-60110');
            }
        }

        $afas = new InMemoryAfasSamenstellingenRepository();
        $afas->replaceSnapshot([$base, $variant]);

        return new FixVariantProductTypeHandler(
            new ProductTypeAuditHandler($groups, $bases, $variants, $afas),
            $writer,
        );
    }
}

final class ProductTypeRecordingWriter implements ProductTypeWriter
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    public array $writes = [];

    public function write(string $itemcode, string $productType01, string $productType02): void
    {
        $this->writes[] = [$itemcode, $productType01, $productType02];
    }
}

final class ProductTypeFailingWriter implements ProductTypeWriter
{
    public function write(string $itemcode, string $productType01, string $productType02): void
    {
        throw ProductTypeWriteFailedException::unresolved($itemcode, 'Product_type 01', $productType01);
    }
}
