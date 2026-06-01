<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextLookup;
use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextNotFoundException;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;

/**
 * Lazy-pull: pas op de eerste lookup-call wordt Get_Artikelen + PowerBI_Item
 * + easylinq_stock_item gepulld; daarna in-memory cache voor de rest van
 * het CLI-run. Dry-run zonder --apply triggert geen pulls.
 *
 * Get_Artikelen levert `Grp` + `CsGc`; `PowerBI_Item` vult de drie webshop-
 * free-fields aan (`Producttype`, `Subcategorie`, `Merknaam`).
 */
final class HttpVariantWriteContextLookup implements VariantWriteContextLookup
{
    private const FIELD_PRODUCT_TYPE = 'U5C3C0BC348244F0F97425794CE3FB4A8';
    private const FIELD_SUBCATEGORIE = 'U79C8521E4FDA2AC22FF895BD89B6D273';
    private const FIELD_MERKNAAM = 'UE10D6C68486BDE5DE3CCC19EBE1E787B';

    /** @var array<string, array{grp: string, cbsCode: string, productType: string, subcategorie: string, merknaam: string}>|null */
    private ?array $referenceCache = null;

    /** @var array<string, string>|null */
    private ?array $typeIdCache = null;

    /** @var array<string, array<string, string>>|null description→id per veld-UUID */
    private ?array $enumLabelToId = null;

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
     * @return array<string, array{grp: string, cbsCode: string, productType: string, subcategorie: string, merknaam: string}>
     */
    private function buildReferenceCache(): array
    {
        // Eerste bron: Get_Artikelen voor Grp + CsGc (verplicht voor de POST).
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
            $cache[$code] = [
                'grp' => (string) $grp,
                'cbsCode' => (string) $cbs,
                'productType' => '',
                'subcategorie' => '',
                'merknaam' => '',
            ];
        }

        // Tweede bron: PowerBI_Item voor de drie webshop-free-fields. AFAS levert
        // hier de description ("AED pakket"), maar de UpdateConnector eist de id
        // ("08"). Resolve via metainfo van FbComposition (zie buildEnumMaps).
        $enumMaps = $this->enumLabelToId ??= $this->buildEnumMaps();

        foreach ($this->client->getConnectorAll('PowerBI_Item') as $row) {
            $code = $row['Itemcode'] ?? null;
            if (!is_string($code) || !isset($cache[$code])) {
                continue;
            }
            $cache[$code]['productType'] = $this->resolveEnumId($row['Product_type___01_'] ?? null, $enumMaps[self::FIELD_PRODUCT_TYPE] ?? []);
            $cache[$code]['subcategorie'] = $this->resolveEnumId($row['Product_type___02_'] ?? null, $enumMaps[self::FIELD_SUBCATEGORIE] ?? []);
            $cache[$code]['merknaam'] = $this->resolveEnumId($row['Merknaam'] ?? null, $enumMaps[self::FIELD_MERKNAAM] ?? []);
        }

        return $cache;
    }

    /**
     * @param array<string, string> $labelToId
     */
    private function resolveEnumId(mixed $rawDescription, array $labelToId): string
    {
        if (!is_scalar($rawDescription)) {
            return '';
        }
        $description = trim((string) $rawDescription);
        if ($description === '') {
            return '';
        }

        return $labelToId[$description] ?? '';
    }

    /**
     * Pull metainfo van FbComposition en bouw description→id voor de drie
     * relevante free-field UUIDs. Zo blijft de mapping in sync met de echte
     * AFAS-enum (geen hardcoding).
     *
     * @return array<string, array<string, string>>
     */
    private function buildEnumMaps(): array
    {
        $maps = [
            self::FIELD_PRODUCT_TYPE => [],
            self::FIELD_SUBCATEGORIE => [],
            self::FIELD_MERKNAAM => [],
        ];

        $payload = $this->client->getMetainfoUpdate('FbComposition');
        $fields = $payload['fields'] ?? null;
        if (!is_array($fields)) {
            return $maps;
        }

        foreach ($fields as $field) {
            $fieldId = $field['fieldId'] ?? null;
            if (!is_string($fieldId) || !isset($maps[$fieldId])) {
                continue;
            }
            $values = $field['values'] ?? null;
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $v) {
                $id = $v['id'] ?? null;
                $desc = $v['description'] ?? null;
                if (is_scalar($id) && is_scalar($desc) && (string) $desc !== '') {
                    $maps[$fieldId][(string) $desc] = (string) $id;
                }
            }
        }

        return $maps;
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
