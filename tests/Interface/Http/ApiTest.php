<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Http;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasArticleRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteBomBlacklistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupVariantRepository;
use Defibrion\Samenstellingen\Interface\Http\AppFactory;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ApiTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    #[Test]
    public function listsGroupsWithCounts(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);
        $items = new SqliteGroupBaseItemRepository($pdo);

        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $base = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        self::assertNotNull($base->id);
        $items->saveForBase($base->id, new GroupBaseItem('50013', 'AED NL'));
        $items->saveForBase($base->id, new GroupBaseItem('70112', 'Reanimatiekit'));

        $response = $this->call('GET', '/api/groups');

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']);
        self::assertSame('Reanibex 100 Semi-Auto', $response['body'][0]['name']);
        self::assertSame('52112', $response['body'][0]['familyHead']);
        self::assertSame(1, $response['body'][0]['baseCount']);
        self::assertSame(2, $response['body'][0]['baseItemCount']);
    }

    #[Test]
    public function showsGroupDetailWithBasesAndItems(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);
        $items = new SqliteGroupBaseItemRepository($pdo);

        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $baseNl = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        $baseFr = $bases->saveForGroup('52112', new GroupBase(null, 'Pack DAE FR', 'FR', '52124'));
        self::assertNotNull($baseNl->id);
        self::assertNotNull($baseFr->id);
        $items->saveForBase($baseNl->id, new GroupBaseItem('50013', '50013'));
        $items->saveForBase($baseFr->id, new GroupBaseItem('50001', '50001'));

        // Snapshot van AFAS-artikelnamen: labels worden hieruit gejoint.
        $articles = new SqliteAfasArticleRepository($pdo);
        $articles->replaceSnapshot([
            new AfasArticle('50013', 'AED Nederlands'),
            new AfasArticle('50001', 'DAE Français'),
        ]);

        $response = $this->call('GET', '/api/groups/52112');

        self::assertSame(200, $response['status']);
        self::assertSame('52112', $response['body']['familyHead']);
        self::assertSame('Reanibex 100 Semi-Auto', $response['body']['name']);
        self::assertCount(2, $response['body']['bases']);

        $nl = array_values(array_filter(
            $response['body']['bases'],
            static fn ($b) => $b['languageCode'] === 'NL',
        ))[0];
        self::assertSame('AED pakket NL', $nl['name']);
        self::assertSame('52112', $nl['afasItemcode']);
        self::assertArrayHasKey('afasItemcodeParent', $nl);
        self::assertNull($nl['afasItemcodeParent']);
        self::assertCount(1, $nl['items']);
        self::assertSame('50013', $nl['items'][0]['itemcode']);
        self::assertSame('AED Nederlands', $nl['items'][0]['label']);
    }

    #[Test]
    public function listGroupsExposesParentMismatchCount(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);

        $groups->save(new Group('Mindray C1 semi', '21018'));
        $groups->save(new Group('Cardiac G5 semi', '11148'));
        $bases->saveForGroup('21018', new GroupBase(null, 'NL', 'NL', '21018'));
        $bases->saveForGroup('21018', new GroupBase(null, 'tri', 'NL/EN/FR', '21011'));
        $bases->saveForGroup('11148', new GroupBase(null, 'NL/EN', 'NL/EN', '11148'));

        $samenstellingen = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository($pdo);
        $samenstellingen->replaceSnapshot([
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('21018', 'Mindray semi NL', '21018', []),
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('21011', 'Mindray 3-talig', '21017', []),
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('11148', 'Cardiac G5 NL-EN', '11148', []),
        ]);

        $response = $this->call('GET', '/api/groups');

        self::assertSame(200, $response['status']);
        $byFh = [];
        foreach ($response['body'] as $g) {
            $byFh[$g['familyHead']] = $g;
        }
        self::assertSame(1, $byFh['21018']['parentMismatchCount']);
        self::assertSame(0, $byFh['11148']['parentMismatchCount']);
    }

    #[Test]
    public function listGroupsCountsBasesWithEmptyParentInAfas(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);

        $groups->save(new Group('Lifepak CR2 semi', '11161'));
        $bases->saveForGroup('11161', new GroupBase(null, 'NL', 'NL', '11161'));
        // Non-head base met lege parent in AFAS → moet meetellen onder slice 53.
        $bases->saveForGroup('11161', new GroupBase(null, 'WiFi', 'NL/EN', '11164'));

        $samenstellingen = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository($pdo);
        $samenstellingen->replaceSnapshot([
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('11161', 'CR2 semi NL', '11161', []),
            // 11164 mist parent → drift
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('11164', 'CR2 semi WiFi', null, []),
        ]);

        $response = $this->call('GET', '/api/groups');

        $byFh = [];
        foreach ($response['body'] as $g) {
            $byFh[$g['familyHead']] = $g;
        }
        self::assertSame(1, $byFh['11161']['parentMismatchCount']);
    }

    #[Test]
    public function listGroupsCountsFamilyHeadSelfParentDriftToo(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);

        $groups->save(new Group('Mindray C2 vol', '21014'));
        $groups->save(new Group('Cardiac G5 semi', '11148'));
        $bases->saveForGroup('21014', new GroupBase(null, '3-talig', 'NL/EN/FR', '21014'));
        $bases->saveForGroup('11148', new GroupBase(null, 'NL/EN', 'NL/EN', '11148'));

        $samenstellingen = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository($pdo);
        $samenstellingen->replaceSnapshot([
            // 21014 mist self-parent → telt mee als mismatch
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('21014', 'Mindray C2 vol', null, []),
            // 11148 heeft wel self-parent → telt niet mee
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('11148', 'Cardiac G5 NL-EN', '11148', []),
        ]);

        $response = $this->call('GET', '/api/groups');

        self::assertSame(200, $response['status']);
        $byFh = [];
        foreach ($response['body'] as $g) {
            $byFh[$g['familyHead']] = $g;
        }
        self::assertSame(1, $byFh['21014']['parentMismatchCount']);
        self::assertSame(0, $byFh['11148']['parentMismatchCount']);
    }

    #[Test]
    public function showGroupExposesFamilyHeadParentInAfas(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);

        $groups->save(new Group('Mindray C2 vol', '21014'));
        $bases->saveForGroup('21014', new GroupBase(null, '3-talig', 'NL/EN/FR', '21014'));

        $samenstellingen = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository($pdo);
        $samenstellingen->replaceSnapshot([
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('21014', 'Mindray C2 vol', null, []),
        ]);

        $response = $this->call('GET', '/api/groups/21014');

        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('familyHeadParentInAfas', $response['body']);
        self::assertNull($response['body']['familyHeadParentInAfas']);
    }

    #[Test]
    public function showsAfasItemcodeParentOnBasesWhenSnapshotHasIt(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);

        $groups->save(new Group('Mindray C1 semi', '21018'));
        $baseInGroup = $bases->saveForGroup('21018', new GroupBase(null, 'NL base', 'NL', '21018'));
        $baseFromOtherFamily = $bases->saveForGroup('21018', new GroupBase(null, 'NL/EN/FR', 'NL/EN/FR', '21011'));
        $baseNoAfas = $bases->saveForGroup('21018', new GroupBase(null, 'Handmatig', 'NL'));
        self::assertNotNull($baseInGroup->id);
        self::assertNotNull($baseFromOtherFamily->id);
        self::assertNotNull($baseNoAfas->id);

        $samenstellingen = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository($pdo);
        $samenstellingen->replaceSnapshot([
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('21018', 'Mindray C1 semi NL', '21018', []),
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling('21011', 'Mindray C1A 3-talig', '21017', []),
        ]);

        $response = $this->call('GET', '/api/groups/21018');

        self::assertSame(200, $response['status']);
        $byCode = [];
        foreach ($response['body']['bases'] as $b) {
            $byCode[$b['afasItemcode'] ?? '__none'] = $b;
        }

        self::assertSame('21018', $byCode['21018']['afasItemcodeParent']);
        self::assertSame('21017', $byCode['21011']['afasItemcodeParent']);
        self::assertNull($byCode['__none']['afasItemcodeParent']);
    }

    #[Test]
    public function returns404ForUnknownGroup(): void
    {
        $response = $this->call('GET', '/api/groups/99999');

        self::assertSame(404, $response['status']);
        self::assertArrayHasKey('error', $response['body']);
    }

    #[Test]
    public function listsAccessoiresCatalogueSortedByItemcode(): void
    {
        $accessoires = new SqliteAccessoireRepository(TestDatabase::pdo());
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));

        $response = $this->call('GET', '/api/accessoires');

        self::assertSame(200, $response['status']);
        self::assertCount(2, $response['body']);
        self::assertSame('60110', $response['body'][0]['itemcode']);
        self::assertSame('EHBO-Rugzak', $response['body'][0]['label']);
    }

    #[Test]
    public function listsBomBlacklist(): void
    {
        $blacklist = new SqliteBomBlacklistRepository(TestDatabase::pdo());
        $blacklist->save(new BomBlacklistEntry('81311', 'Waalse stickerset'));

        $response = $this->call('GET', '/api/bom-blacklist');

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']);
        self::assertSame('81311', $response['body'][0]['itemcode']);
        self::assertSame('Waalse stickerset', $response['body'][0]['reason']);
    }

    #[Test]
    public function listsAccessoiresLinkedToGroup(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $accessoires = new SqliteAccessoireRepository($pdo);
        $links = new SqliteGroupAccessoireRepository($pdo);
        $groups->save(new Group('Reanibex', '52112'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $links->link('52112', '60112');

        $response = $this->call('GET', '/api/groups/52112/accessoires');

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']);
        self::assertSame('60112', $response['body'][0]['itemcode']);
        self::assertSame('ARKY witte binnenkast', $response['body'][0]['label']);
    }

    #[Test]
    public function listsGroupAccessoiresReturns404ForUnknownGroup(): void
    {
        $response = $this->call('GET', '/api/groups/99999/accessoires');
        self::assertSame(404, $response['status']);
    }

    #[Test]
    public function listsMissingVariantsAcrossAllGroups(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);
        $items = new SqliteGroupBaseItemRepository($pdo);
        $accessoires = new SqliteAccessoireRepository($pdo);
        $links = new SqliteGroupAccessoireRepository($pdo);
        $variants = new SqliteGroupVariantRepository($pdo);

        $groups->save(new Group('Reanibex', '52112'));
        $base = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($base->id);
        $items->saveForBase($base->id, new GroupBaseItem('50013', 'AED NL'));
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $links->link('52112', '60110');
        $variants->regenerateForGroup('52112');

        // Markeer alleen de base-only-variant als matched zodat de variant met accessoire
        // als 'no_match' overblijft in de missing-lijst.
        $allVariants = $variants->findAllForGroup('52112');
        foreach ($allVariants as $variant) {
            if ($variant->id === null) {
                continue;
            }
            if ($variant->accessoireItemcode === null) {
                $variants->markMatched($variant->id, '52112');
            } else {
                $variants->markNoMatch($variant->id);
            }
        }

        $response = $this->call('GET', '/api/missing-variants');

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']);
        self::assertSame('Reanibex', $response['body'][0]['groupName']);
        self::assertSame('AED pakket NL', $response['body'][0]['baseName']);
        self::assertSame('60110', $response['body'][0]['accessoireItemcode']);
        self::assertSame('52112-60110', $response['body'][0]['suggestedSku']);
    }

    #[Test]
    public function listsSuspiciousBases(): void
    {
        $pdo = TestDatabase::pdo();
        $accessoires = new SqliteAccessoireRepository($pdo);
        $afasRepo = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository($pdo);
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $afasRepo->replaceSnapshot([
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling(
                '11683-60110',
                'Zoll AED Plus + ARKY Backpack',
                '11683',
                ['10683', '70112', '81511'],
            ),
        ]);

        $response = $this->call('GET', '/api/suspicious-bases');

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']);
        self::assertSame('11683-60110', $response['body'][0]['afasItemcode']);
        self::assertSame('60110', $response['body'][0]['expectedAccessoireItemcode']);
    }

    #[Test]
    public function listsNameDriftForMismatchedAfasName(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);
        $variants = new SqliteGroupVariantRepository($pdo);

        $groups->save(new \Defibrion\Samenstellingen\Domain\Group\Group(
            'Reanibex',
            '52112',
            'Reanibex 100 semi-automaat',
        ));
        $base = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($base->id);
        $variants->regenerateForGroup('52112');

        $afasRepo = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository($pdo);
        $afasRepo->replaceSnapshot([
            new \Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling(
                '52112',
                'AED Pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset', // verkeerde casing
                null,
                ['50013'],
            ),
        ]);
        foreach ($variants->findAllForGroup('52112') as $v) {
            if ($v->id !== null && $v->accessoireItemcode === null) {
                $variants->markMatched($v->id, '52112');
            }
        }

        $response = $this->call('GET', '/api/name-drift');

        self::assertSame(200, $response['status']);
        self::assertCount(1, $response['body']);
        self::assertSame('52112', $response['body'][0]['afasItemcode']);
        self::assertSame(
            'AED Pakket: Reanibex 100 semi-automaat NL',
            $response['body'][0]['expected'],
        );
    }

    #[Test]
    public function listsGroupVariants(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);
        $accessoires = new SqliteAccessoireRepository($pdo);
        $links = new SqliteGroupAccessoireRepository($pdo);
        $variants = new SqliteGroupVariantRepository($pdo);

        $groups->save(new Group('Reanibex', '52112'));
        $base = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL', '52112'));
        self::assertNotNull($base->id);
        $accessoires->save(new Accessoire('60110', 'EHBO-Rugzak'));
        $links->link('52112', '60110');
        $variants->regenerateForGroup('52112');

        $response = $this->call('GET', '/api/groups/52112/variants');

        self::assertSame(200, $response['status']);
        // 1 base × (geen accessoire + 60110) = 2 varianten.
        self::assertCount(2, $response['body']);

        $baseOnly = array_values(array_filter(
            $response['body'],
            static fn ($v) => $v['accessoireItemcode'] === null,
        ))[0];
        self::assertSame('NL', $baseOnly['languageCode']);
        self::assertSame('AED pakket NL', $baseOnly['baseName']);
        self::assertNull($baseOnly['afasSamenstellingItemcode']);
    }

    #[Test]
    public function exposesWooEndpoints(): void
    {
        $pdo = TestDatabase::pdo();
        $pdo->exec('DELETE FROM woocommerce_products');
        $pdo->exec('DELETE FROM woocommerce_stores');
        $stores = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteWooCommerceStoreRepository($pdo);
        $products = new \Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteWooProductRepository($pdo);

        // Seed: 1 group + 1 base met afas_itemcode 11111; 1 WC-store met 2 producten
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);
        $groups->save(new Group('Heartsine 350P', '10013'));
        $bases->saveForGroup('10013', new GroupBase(null, 'NL', 'NL', '11111'));

        $saved = $stores->save(new \Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore(null, 'defibrion.nl', 'https://defibrion.nl', 'ck', 'cs'));
        self::assertNotNull($saved->id);
        $products->replaceForStore($saved->id, [
            new \Defibrion\Samenstellingen\Domain\Woo\WooProduct(1, 'simple', 'sku-1', 'Base op shop', 'publish', null, '11111'),
            new \Defibrion\Samenstellingen\Domain\Woo\WooProduct(2, 'simple', 'sku-2', 'Orphan-product', 'publish', null, '99999'),
        ]);

        // /api/wc/stores
        $storesResp = $this->call('GET', '/api/wc/stores');
        self::assertSame(200, $storesResp['status']);
        self::assertCount(1, $storesResp['body']);
        self::assertSame('defibrion.nl', $storesResp['body'][0]['name']);
        self::assertSame(2, $storesResp['body'][0]['itemCount']);

        // /api/wc/index
        $indexResp = $this->call('GET', '/api/wc/index');
        self::assertSame(200, $indexResp['status']);
        self::assertCount(1, $indexResp['body']['rows']);
        self::assertSame('11111', $indexResp['body']['rows'][0]['afasItemcode']);
        self::assertSame('publish', $indexResp['body']['rows'][0]['cells'][0]['cell']['status']);

        // /api/wc/orphans
        $orphansResp = $this->call('GET', '/api/wc/orphans');
        self::assertSame(200, $orphansResp['status']);
        self::assertCount(1, $orphansResp['body']);
        self::assertSame(2, $orphansResp['body'][0]['wcProductId']);
        self::assertSame('99999', $orphansResp['body'][0]['afasItemcode']);
    }

    /**
     * @return array{status: int, body: mixed}
     */
    private function call(string $method, string $uri): array
    {
        $app = AppFactory::create(TestDatabase::container());
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        $response = $app->handle($request);
        $body = (string) $response->getBody();
        /** @var mixed $decoded */
        $decoded = $body === '' ? null : json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        return [
            'status' => $response->getStatusCode(),
            'body' => $decoded,
        ];
    }
}
