<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenFetcher;

/**
 * Haal álle AFAS samenstellingen + BOMs op via REST. Geen pre-filtering;
 * we trekken alles binnen en laten de matcher op BOM-content beslissen.
 */
final readonly class HttpAfasSamenstellingenFetcher implements AfasSamenstellingenFetcher
{
    private const COMPOSITION_TYPE_ID = '7';

    public function __construct(private AfasHttpClient $client)
    {
    }

    public function fetchAll(): array
    {
        $articles = $this->client->getConnectorAll('Get_Artikelen');
        $stockItems = $this->client->getConnectorAll('easylinq_stock_item');
        $parts = $this->client->getConnectorAll('easylinq_stock_item_parts');

        $compositionItemcodes = $this->collectCompositionItemcodes($stockItems);
        $articleIndex = $this->indexArticles($articles);
        $bomByItemcode = $this->indexBoms($parts);

        $result = [];
        foreach ($compositionItemcodes as $itemcode) {
            $article = $articleIndex[$itemcode] ?? null;
            $result[] = new AfasSamenstelling(
                $itemcode,
                $article['name'] ?? '',
                $article['parent'] ?? null,
                $bomByItemcode[$itemcode] ?? [],
                null,
                $article['cbs'] ?? null,
            );
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $stockItems
     *
     * @return list<string>
     */
    private function collectCompositionItemcodes(array $stockItems): array
    {
        $codes = [];
        foreach ($stockItems as $row) {
            $code = $row['item_id'] ?? null;
            $type = $row['type_id'] ?? null;
            if (is_string($code) && (string) $type === self::COMPOSITION_TYPE_ID) {
                $trimmed = trim($code);
                if ($trimmed !== '') {
                    $codes[] = $trimmed;
                }
            }
        }

        return $codes;
    }

    /**
     * @param list<array<string, mixed>> $articles
     *
     * @return array<string, array{name: string, parent: ?string, cbs: ?string}>
     */
    private function indexArticles(array $articles): array
    {
        $index = [];
        foreach ($articles as $row) {
            $itemcode = $row['Itemcode'] ?? null;
            if (!is_string($itemcode)) {
                continue;
            }
            $trimmed = trim($itemcode);
            if ($trimmed === '') {
                continue;
            }
            $name = $row['Naam'] ?? '';
            $parent = $row['Itemcode_Parent'] ?? null;
            $cbs = $row['CBS-goederencode'] ?? null;
            $index[$trimmed] = [
                'name' => is_string($name) ? $name : '',
                'parent' => is_string($parent) && trim($parent) !== '' ? trim($parent) : null,
                'cbs' => is_string($cbs) && trim($cbs) !== '' ? trim($cbs) : null,
            ];
        }

        return $index;
    }

    /**
     * @param list<array<string, mixed>> $parts
     *
     * @return array<string, list<string>>
     */
    private function indexBoms(array $parts): array
    {
        $bomByItemcode = [];
        foreach ($parts as $row) {
            $parent = $row['item_id'] ?? null;
            $part = $row['part_item_id'] ?? null;
            if (!is_string($parent) || !is_string($part)) {
                continue;
            }
            $parentCode = trim($parent);
            $partCode = trim($part);
            if ($parentCode === '' || $partCode === '') {
                continue;
            }
            $bomByItemcode[$parentCode][] = $partCode;
        }

        return $bomByItemcode;
    }
}
