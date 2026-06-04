<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Woo\Http;

use Defibrion\Samenstellingen\Infrastructure\Woo\Http\HttpWooCommerceClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpWooCommerceClientTest extends TestCase
{
    #[Test]
    public function fetchAllProductsPaginatesUntilEmpty(): void
    {
        $page1 = array_map(fn (int $i) => [
            'id' => 100 + $i,
            'type' => 'simple',
            'sku' => "sku-{$i}",
            'name' => "Product {$i}",
            'status' => 'publish',
            'permalink' => "https://x/p/{$i}",
            'meta_data' => [['key' => '_afas_itemcode', 'value' => "AFAS-{$i}"]],
        ], range(1, 100));
        $page2 = [['id' => 999, 'type' => 'simple', 'sku' => null, 'name' => 'Laatste', 'status' => 'publish', 'permalink' => null, 'meta_data' => []]];

        $mock = new MockHandler([
            new Response(200, [], (string) json_encode($page1)),
            new Response(200, [], (string) json_encode($page2)),
            // Geen extra response — een 3e call zou crashen, dus de test bewijst dat we niet doorvragen.
        ]);
        $client = new HttpWooCommerceClient(new Client(['handler' => HandlerStack::create($mock)]), '_afas_itemcode');

        $products = $client->fetchAllProducts();

        self::assertCount(101, $products);
        self::assertSame(101, $products[0]->wcProductId);
        self::assertSame('AFAS-1', $products[0]->afasItemcode);
        self::assertSame(999, $products[100]->wcProductId);
        self::assertNull($products[100]->afasItemcode);
    }

    #[Test]
    public function fetchAllProductsSkipsUnsupportedTypes(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                ['id' => 1, 'type' => 'simple', 'sku' => null, 'name' => 'A', 'status' => 'publish', 'permalink' => null, 'meta_data' => [['key' => '_afas_itemcode', 'value' => 'X1']]],
                ['id' => 2, 'type' => 'grouped', 'sku' => null, 'name' => 'B', 'status' => 'publish', 'permalink' => null, 'meta_data' => []],
                ['id' => 3, 'type' => 'external', 'sku' => null, 'name' => 'C', 'status' => 'publish', 'permalink' => null, 'meta_data' => []],
                ['id' => 4, 'type' => 'variable', 'sku' => null, 'name' => 'D', 'status' => 'publish', 'permalink' => null, 'meta_data' => []],
            ])),
        ]);
        $client = new HttpWooCommerceClient(new Client(['handler' => HandlerStack::create($mock)]), '_afas_itemcode');

        $products = $client->fetchAllProducts();

        self::assertCount(2, $products);
        self::assertSame([1, 4], array_map(static fn ($p) => $p->wcProductId, $products));
    }

    #[Test]
    public function fetchAllVariationsForReturnsVariations(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                ['id' => 201, 'sku' => 'sku-201', 'name' => 'Backpack', 'status' => 'publish', 'permalink' => 'https://x/v/201', 'meta_data' => [['key' => '_afas_itemcode', 'value' => '21011-60110']]],
                ['id' => 202, 'sku' => null, 'name' => 'Indoor', 'status' => 'draft', 'permalink' => null, 'meta_data' => [['key' => '_afas_itemcode', 'value' => '21011-60112']]],
            ])),
        ]);
        $client = new HttpWooCommerceClient(new Client(['handler' => HandlerStack::create($mock)]), '_afas_itemcode');

        $variations = $client->fetchAllVariationsFor(102);

        self::assertCount(2, $variations);
        self::assertSame(201, $variations[0]->wcProductId);
        self::assertSame(102, $variations[0]->parentId);
        self::assertSame('21011-60110', $variations[0]->afasItemcode);
        self::assertSame('draft', $variations[1]->status);
    }

    #[Test]
    public function fetchAllProductsReturnsEmptyOnEmptyFirstPage(): void
    {
        $mock = new MockHandler([new Response(200, [], '[]')]);
        $client = new HttpWooCommerceClient(new Client(['handler' => HandlerStack::create($mock)]), '_afas_itemcode');

        self::assertSame([], $client->fetchAllProducts());
    }
}
