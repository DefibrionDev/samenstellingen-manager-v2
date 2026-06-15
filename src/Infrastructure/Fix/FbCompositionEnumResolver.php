<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;

/**
 * Resolvet een AFAS-enum-description ("AED pakket") naar de id ("08") die de
 * FbComposition-UpdateConnector eist. De mapping komt uit
 * `metainfo/update/FbComposition`, lazy gepulld en gecachet voor de rest van
 * het CLI-run. Gedeeld tussen variant-generatie (`HttpVariantWriteContextLookup`)
 * en de producttype-fix (`HttpProductTypeWriter`). Zie PLAN-AFAS.md §35.
 */
final class FbCompositionEnumResolver
{
    /** @var array<string, array<string, string>>|null description→id per veld-UUID */
    private ?array $maps = null;

    public function __construct(private readonly AfasHttpClient $client)
    {
    }

    public function resolve(string $fieldUuid, ?string $description): string
    {
        if ($description === null) {
            return '';
        }
        $description = trim($description);
        if ($description === '') {
            return '';
        }

        $maps = $this->maps ??= self::buildMaps($this->client->getMetainfoUpdate('FbComposition'));

        return $maps[$fieldUuid][$description] ?? '';
    }

    /**
     * Pure mapping-bouw uit een metainfo-payload — los van de HTTP-fetch zodat
     * het per unit-test te controleren is.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, array<string, string>>
     */
    public static function buildMaps(array $payload): array
    {
        $maps = [];
        $fields = $payload['fields'] ?? null;
        if (!is_array($fields)) {
            return $maps;
        }

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldId = $field['fieldId'] ?? null;
            if (!is_string($fieldId)) {
                continue;
            }
            $values = $field['values'] ?? null;
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                if (!is_array($value)) {
                    continue;
                }
                $id = $value['id'] ?? null;
                $description = $value['description'] ?? null;
                if (is_scalar($id) && is_scalar($description) && (string) $description !== '') {
                    $maps[$fieldId][(string) $description] = (string) $id;
                }
            }
        }

        return $maps;
    }
}
