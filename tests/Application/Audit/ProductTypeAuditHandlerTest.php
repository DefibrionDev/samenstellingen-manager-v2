<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Audit;

use Defibrion\Samenstellingen\Application\Audit\AuditProductType;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeAuditHandler;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeIssueRow;
use Defibrion\Samenstellingen\Application\Audit\ProductTypeIssueType;
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

final class ProductTypeAuditHandlerTest extends TestCase
{
    #[Test]
    public function baseFilledAndVariantEqualIsClean(): void
    {
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, 'AED pakket', '350P'),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, 'AED pakket', '350P'),
        );

        self::assertSame([], ($handler)(new AuditProductType()));
    }

    #[Test]
    public function baseFilledAndVariantEmptyIsFixable(): void
    {
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, 'AED pakket', '350P'),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, null, null),
        );

        $rows = ($handler)(new AuditProductType());

        self::assertCount(1, $rows);
        self::assertSame('11111-60110', $rows[0]->afasItemcode);
        self::assertSame(ProductTypeIssueType::VariantFixable, $rows[0]->issueType);
        self::assertSame('11111', $rows[0]->baseItemcode);
        self::assertSame('AED pakket', $rows[0]->expected01);
        self::assertSame('350P', $rows[0]->expected02);
        self::assertNull($rows[0]->current01);
    }

    #[Test]
    public function baseFilledAndVariantDifferentIsFixable(): void
    {
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, 'AED pakket', '350P'),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, 'Toebehoren', '350P'),
        );

        $rows = ($handler)(new AuditProductType());

        self::assertCount(1, $rows);
        self::assertSame(ProductTypeIssueType::VariantFixable, $rows[0]->issueType);
        self::assertSame('Toebehoren', $rows[0]->current01);
    }

    #[Test]
    public function baseWithOneEmptyFieldIsBaseEmptyAndBlocksVariant(): void
    {
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, 'AED pakket', null),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, 'AED pakket', '350P'),
        );

        $rows = ($handler)(new AuditProductType());

        self::assertSame(ProductTypeIssueType::BaseEmpty, $this->rowFor($rows, '11111')->issueType);
        self::assertSame(ProductTypeIssueType::VariantBlocked, $this->rowFor($rows, '11111-60110')->issueType);
    }

    /**
     * @param list<ProductTypeIssueRow> $rows
     */
    private function rowFor(array $rows, string $itemcode): ProductTypeIssueRow
    {
        foreach ($rows as $row) {
            if ($row->afasItemcode === $itemcode) {
                return $row;
            }
        }
        self::fail("Geen issue-rij voor {$itemcode}");
    }

    #[Test]
    public function baseEmptyAndVariantAlsoEmptyOnlyFlagsBase(): void
    {
        $handler = $this->scaffold(
            base: new AfasSamenstelling('11111', 'AED Pakket', '11111', [], null, null, null, null),
            variant: new AfasSamenstelling('11111-60110', 'AED Pakket + Rugtas', '11111', [], null, null, null, null),
        );

        $rows = ($handler)(new AuditProductType());

        self::assertCount(1, $rows);
        self::assertSame('11111', $rows[0]->afasItemcode);
        self::assertSame(ProductTypeIssueType::BaseEmpty, $rows[0]->issueType);
    }

    private function scaffold(AfasSamenstelling $base, AfasSamenstelling $variant): ProductTypeAuditHandler
    {
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

        return new ProductTypeAuditHandler($groups, $bases, $variants, $afas);
    }
}
