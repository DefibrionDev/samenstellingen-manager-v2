<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Woo\Http;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceClient;
use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;
use Defibrion\Samenstellingen\Infrastructure\Woo\WooMetaDataExtractor;
use GuzzleHttp\ClientInterface;

/**
 * REST-client voor de WooCommerce Store API (v3). Auth via Basic over HTTPS
 * (ck/cs). Pagineert tot het lege resultaat door `?per_page=100&page=N` op te
 * tellen; `context=edit` zorgt dat `meta_data` in de respons zit (alleen
 * beschikbaar voor authenticated users met `read_products` permissie, wat de
 * ck/cs heeft).
 *
 * Variations zitten op een aparte endpoint per variable-parent. Caller is
 * verantwoordelijk om eerst `fetchAllProducts()` te doen, vervolgens per
 * variable-product `fetchAllVariationsFor($parentId)`.
 */
final readonly class HttpWooCommerceClient implements WooCommerceClient
{
    private const int PAGE_SIZE = 100;

    public function __construct(
        private ClientInterface $http,
        private string $metaKey,
    ) {
    }

    public function fetchAllProducts(): array
    {
        $items = [];
        foreach ($this->fetchPagedJson('products', ['status' => 'any', 'context' => 'edit']) as $row) {
            $type = is_string($row['type'] ?? null) ? $row['type'] : 'simple';
            if (!in_array($type, ['simple', 'variable'], true)) {
                continue; // grouped/external/anders → skip; we managen ze niet
            }
            $items[] = new WooProduct(
                wcProductId: (int) $row['id'],
                type: $type,
                sku: $this->stringOrNull($row['sku'] ?? null),
                name: (string) ($row['name'] ?? ''),
                status: (string) ($row['status'] ?? 'publish'),
                permalink: $this->stringOrNull($row['permalink'] ?? null),
                afasItemcode: $this->extractAfasItemcode($row),
            );
        }

        return $items;
    }

    public function fetchAllVariationsFor(int $variableProductId): array
    {
        $items = [];
        foreach ($this->fetchPagedJson("products/{$variableProductId}/variations", ['context' => 'edit']) as $row) {
            $items[] = new WooProductVariation(
                wcProductId: (int) $row['id'],
                parentId: $variableProductId,
                sku: $this->stringOrNull($row['sku'] ?? null),
                name: (string) ($row['name'] ?? ($row['attributes_summary'] ?? '')),
                status: (string) ($row['status'] ?? 'publish'),
                permalink: $this->stringOrNull($row['permalink'] ?? null),
                afasItemcode: $this->extractAfasItemcode($row),
            );
        }

        return $items;
    }

    /**
     * @param array<string, string> $extraQuery
     *
     * @return iterable<array<string, mixed>>
     */
    private function fetchPagedJson(string $endpoint, array $extraQuery = []): iterable
    {
        $page = 1;
        while (true) {
            $response = $this->http->request('GET', $endpoint, [
                'query' => array_merge($extraQuery, [
                    'per_page' => self::PAGE_SIZE,
                    'page' => $page,
                ]),
            ]);
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);
            if (!is_array($decoded) || $decoded === []) {
                return;
            }
            foreach ($decoded as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }
            if (count($decoded) < self::PAGE_SIZE) {
                return;
            }
            ++$page;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractAfasItemcode(array $row): ?string
    {
        $meta = $row['meta_data'] ?? null;
        if (!is_array($meta)) {
            return null;
        }
        /** @var list<array{key?: mixed, value?: mixed}> $meta */
        return WooMetaDataExtractor::extract($meta, $this->metaKey);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
