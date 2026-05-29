<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextLookup;
use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;

/**
 * Lazy-pull: pas op de eerste lookup-call wordt Get_Artikelen +
 * easylinq_stock_item gepulld; daarna in-memory cache voor de rest van
 * het CLI-run. Dry-run zonder --apply triggert geen pulls.
 */
final class HttpVariantWriteContextLookup implements VariantWriteContextLookup
{
    /** @var array<string, array{grp: string, cbsCode: string}>|null */
    private ?array $referenceCache = null;

    /** @var array<string, string>|null */
    private ?array $typeIdCache = null;

    public function __construct(private readonly AfasHttpClient $client)
    {
    }

    public function lookupReferenceFields(string $referenceItemcode): array
    {
        $cache = $this->referenceCache ??= $this->buildReferenceCache();
        if (!isset($cache[$referenceItemcode])) {
            throw VariantWriteContextNotFoundException::forReference($referenceItemcode);
        }

        return $cache[$referenceItemcode];
    }

    public function lookupBomItemType(string $itemcode): string
    {
        $cache = $this->typeIdCache ??= $this->buildTypeIdCache();

        return ($cache[$itemcode] ?? '') === '7' ? 'Sam' : 'Art';
    }

    /**
     * @return array<string, array{grp: string, cbsCode: string}>
     */
    private function buildReferenceCache(): array
    {
        $cache = [];
        foreach ($this->client->getConnectorAll('Get_Artikelen') as $row) {
            $code = $row['Itemcode'] ?? null;
            if (!is_string($code) || $code === '') {
                continue;
            }
            $grp = $row['Artikelgroep'] ?? null;
            $cbs = $row['CBS-goederencode'] ?? null;
            if (!is_scalar($grp) || (string) $grp === '' || !is_scalar($cbs) || (string) $cbs === '') {
                continue;
            }
            $cache[$code] = ['grp' => (string) $grp, 'cbsCode' => (string) $cbs];
        }

        return $cache;
    }

    /**
     * @return array<string, string>
     */
    private function buildTypeIdCache(): array
    {
        $cache = [];
        foreach ($this->client->getConnectorAll('easylinq_stock_item') as $row) {
            $code = $row['item_id'] ?? null;
            $type = $row['type_id'] ?? null;
            if (is_string($code) && $code !== '' && is_scalar($type)) {
                $cache[$code] = (string) $type;
            }
        }

        return $cache;
    }
}
