<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Application\Woo;

use Defibrion\Samenstellingen\Application\Woo\AuditWcHealth;
use Defibrion\Samenstellingen\Application\Woo\WcHealthAuditHandler;
use Defibrion\Samenstellingen\Application\Woo\WcHealthRow;
use Defibrion\Samenstellingen\Application\Woo\WcHealthStatus;
use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryGroupVariantRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooCommerceStoreRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory\InMemoryWooProductRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WcHealthAuditHandlerTest extends TestCase
{
    #[Test]
    public function familyHeadAsVariableAndPublishIsOk(): void
    {
        $bag = $this->scaffold();
        $bag['products']->replaceForStore($bag['storeId'], [
            new WooProduct(101, 'variable', null, 'Heartsine 350P', 'publish', null, '11111'),
        ]);

        $rows = ($bag['handler'])(new AuditWcHealth(null));

        $found = $this->findByCode($rows, '11111');
        self::assertNotNull($found);
        self::assertSame('variable', $found->expectedType);
        self::assertSame(WcHealthStatus::Ok, $found->cellsByStore[$bag['storeId']]->healthStatus);
    }

    #[Test]
    public function nonHeadVariantAsSimpleTriggersWrongType(): void
    {
        $bag = $this->scaffold();
        // 11111-60110 hoort variation onder de variable parent te zijn; staat hier als simple.
        $bag['products']->replaceForStore($bag['storeId'], [
            new WooProduct(101, 'variable', null, 'PAD 350P parent', 'publish', null, '11111'),
            new WooProduct(102, 'simple', null, 'PAD 350P Backpack standalone', 'publish', null, '11111-60110'),
        ]);

        $rows = ($bag['handler'])(new AuditWcHealth(null));

        $found = $this->findByCode($rows, '11111-60110');
        self::assertNotNull($found);
        self::assertSame('variation', $found->expectedType);
        $cell = $found->cellsByStore[$bag['storeId']];
        self::assertSame(WcHealthStatus::WrongType, $cell->healthStatus);
        self::assertSame('simple', $cell->actualType);
    }

    #[Test]
    public function variationOrVariablePublishIsOkForMatchedVariant(): void
    {
        $bag = $this->scaffold();
        $bag['products']->replaceForStore($bag['storeId'], [
            new WooProduct(101, 'variable', null, 'PAD 350P parent', 'publish', null, '11111'),
            new WooProductVariation(201, 101, null, 'Backpack', 'publish', null, '11111-60110'),
        ]);

        $rows = ($bag['handler'])(new AuditWcHealth(null));

        $found = $this->findByCode($rows, '11111-60110');
        self::assertNotNull($found);
        self::assertSame(WcHealthStatus::Ok, $found->cellsByStore[$bag['storeId']]->healthStatus);
    }

    #[Test]
    public function missingItemcodeTriggersMissing(): void
    {
        $bag = $this->scaffold();
        // alleen de base in WC; variant 11111-60110 ontbreekt
        $bag['products']->replaceForStore($bag['storeId'], [
            new WooProduct(101, 'variable', null, 'Parent', 'publish', null, '11111'),
        ]);

        $rows = ($bag['handler'])(new AuditWcHealth(null));

        $found = $this->findByCode($rows, '11111-60110');
        self::assertNotNull($found);
        $cell = $found->cellsByStore[$bag['storeId']];
        self::assertSame(WcHealthStatus::Missing, $cell->healthStatus);
        self::assertNull($cell->wcProductId);
    }

    #[Test]
    public function correctTypeWithDraftStatusTriggersNotPublish(): void
    {
        $bag = $this->scaffold();
        $bag['products']->replaceForStore($bag['storeId'], [
            new WooProduct(101, 'variable', null, 'Parent', 'draft', null, '11111'),
        ]);

        $rows = ($bag['handler'])(new AuditWcHealth(null));

        $found = $this->findByCode($rows, '11111');
        self::assertNotNull($found);
        self::assertSame(WcHealthStatus::NotPublish, $found->cellsByStore[$bag['storeId']]->healthStatus);
    }

    #[Test]
    public function storeFilterScopesOutput(): void
    {
        $bag = $this->scaffold(extraStoreNames: ['defibrion.fr']);
        $bag['products']->replaceForStore($bag['storeId'], [
            new WooProduct(101, 'variable', null, 'NL parent', 'publish', null, '11111'),
        ]);
        $bag['products']->replaceForStore($bag['storeFrId'] ?? 0, [
            new WooProduct(201, 'variable', null, 'FR parent', 'publish', null, '11111'),
        ]);

        $rowsAll = ($bag['handler'])(new AuditWcHealth(null));
        $rowsScopedFr = ($bag['handler'])(new AuditWcHealth('defibrion.fr'));

        $rowAll = $this->findByCode($rowsAll, '11111');
        self::assertNotNull($rowAll);
        self::assertCount(2, $rowAll->cellsByStore);

        $rowFr = $this->findByCode($rowsScopedFr, '11111');
        self::assertNotNull($rowFr);
        self::assertCount(1, $rowFr->cellsByStore);
        self::assertArrayHasKey($bag['storeFrId'] ?? 0, $rowFr->cellsByStore);
    }

    /**
     * @param list<WcHealthRow> $rows
     */
    private function findByCode(array $rows, string $code): ?WcHealthRow
    {
        foreach ($rows as $row) {
            if ($row->afasItemcode === $code) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Bootstrap: 1 group "Heartsine 350P" (head=11111) + 1 base + 1 accessoire (60110) +
     * matched variant (11111-60110). 1 WC-store "defibrion.nl"; opt. extra stores.
     *
     * @param list<string> $extraStoreNames
     *
     * @return array{
     *     handler: WcHealthAuditHandler,
     *     products: InMemoryWooProductRepository,
     *     storeId: int,
     *     storeFrId?: int,
     * }
     */
    private function scaffold(array $extraStoreNames = []): array
    {
        $groups = new InMemoryGroupRepository();
        $bases = new InMemoryGroupBaseRepository($groups);
        $accessoires = new InMemoryAccessoireRepository();
        $links = new InMemoryGroupAccessoireRepository($groups, $accessoires);
        $variants = new InMemoryGroupVariantRepository($groups, $bases, $links);
        $stores = new InMemoryWooCommerceStoreRepository();
        $products = new InMemoryWooProductRepository();

        $groups->save(new Group('Heartsine 350P', '11111'));
        $base = $bases->saveForGroup('11111', new GroupBase(null, 'NL', 'NL', '11111'));
        self::assertNotNull($base->id);
        $accessoires->save(new Accessoire('60110', 'Rugzak'));
        $links->link('11111', '60110');
        $variants->regenerateForGroup('11111');
        foreach ($variants->findAllForGroup('11111') as $variant) {
            self::assertNotNull($variant->id);
            if ($variant->accessoireItemcode === '60110') {
                $variants->markMatched($variant->id, '11111-60110');
            }
        }

        $nl = $stores->save(new WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        self::assertNotNull($nl->id);
        $result = [
            'handler' => new WcHealthAuditHandler($groups, $bases, $variants, $stores, $products),
            'products' => $products,
            'storeId' => $nl->id,
        ];

        foreach ($extraStoreNames as $name) {
            $extra = $stores->save(new WooCommerceStore(null, $name, 'https://' . $name, 'ck', 'cs'));
            self::assertNotNull($extra->id);
            if ($name === 'defibrion.fr') {
                $result['storeFrId'] = $extra->id;
            }
        }

        return $result;
    }
}
