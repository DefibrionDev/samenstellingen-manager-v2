<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Interface\Http;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
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
        $baseNl = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        $baseFr = $bases->saveForGroup('52112', new GroupBase(null, 'Pack DAE FR', 'FR'));
        self::assertNotNull($baseNl->id);
        self::assertNotNull($baseFr->id);
        $items->saveForBase($baseNl->id, new GroupBaseItem('50013', 'AED NL'));
        $items->saveForBase($baseFr->id, new GroupBaseItem('50001', 'DAE FR'));

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
        self::assertCount(1, $nl['items']);
        self::assertSame('50013', $nl['items'][0]['itemcode']);
        self::assertSame('AED NL', $nl['items'][0]['label']);
    }

    #[Test]
    public function returns404ForUnknownGroup(): void
    {
        $response = $this->call('GET', '/api/groups/99999');

        self::assertSame(404, $response['status']);
        self::assertArrayHasKey('error', $response['body']);
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
