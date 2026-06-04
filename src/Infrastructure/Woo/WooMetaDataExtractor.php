<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Woo;

/**
 * Pakt de AFAS-itemcode uit de `meta_data`-array zoals WooCommerce die per
 * product retourneert. Accepteert alleen scalar string- of int-waarden (int
 * wordt ge-stringt); arrays of leeg-strings → null.
 */
final class WooMetaDataExtractor
{
    /**
     * @param list<array{key?: mixed, value?: mixed}> $metaData
     */
    public static function extract(array $metaData, string $metaKey): ?string
    {
        foreach ($metaData as $entry) {
            $key = $entry['key'] ?? null;
            if (!is_string($key) || $key !== $metaKey) {
                continue;
            }
            $value = $entry['value'] ?? null;
            if (is_string($value)) {
                return $value === '' ? null : $value;
            }
            if (is_int($value)) {
                return (string) $value;
            }

            return null;
        }

        return null;
    }
}
