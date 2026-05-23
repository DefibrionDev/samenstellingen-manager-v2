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
        self::assertCount(1, $nl['items']);
        self::assertSame('50013', $nl['items'][0]['itemcode']);
        self::assertSame('AED Nederlands', $nl['items'][0]['label']);
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
            'AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset',
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
