<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextLookup;
use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextNotFoundException;

/**
 * Test-double — pre-loaded data, geen netwerk.
 */
final class InMemoryVariantWriteContextLookup implements VariantWriteContextLookup
{
    /**
     * @param array<string, array{grp: string, cbsCode: string, productType: string, subcategorie: string, merknaam: string}> $referenceFields
     * @param array<int|string, string>                          $typeIdByItemcode AFAS-itemcodes
     *        zijn alfanumeriek; PHP cast pure-numerieke string-keys naar int — daarom int|string.
     */
    public function __construct(
        private readonly array $referenceFields,
        private readonly array $typeIdByItemcode,
    ) {
    }

    public function lookupReferenceFields(string $referenceItemcode): array
    {
        if (!isset($this->referenceFields[$referenceItemcode])) {
            throw VariantWriteContextNotFoundException::forReference($referenceItemcode);
        }

        return $this->referenceFields[$referenceItemcode];
    }

    public function lookupBomItemType(string $itemcode): string
    {
        return ($this->typeIdByItemcode[$itemcode] ?? '') === '7' ? 'Sam' : 'Art';
    }
}
