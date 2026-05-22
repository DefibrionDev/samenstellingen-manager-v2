<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

final readonly class VariantMatcher
{
    /**
     * Zoek de AFAS-samenstelling waarvan de BOM precies overeenkomt met de verwachte itemcodes.
     *
     * @param list<string>             $expectedBomItemcodes
     * @param list<AfasSamenstelling>  $candidates
     *
     * @throws AmbiguousMatchException wanneer ≥2 AFAS-samenstellingen passen.
     *
     * @return string|null AFAS-SKU van de unieke match, of null als er geen match is.
     */
    public function findMatch(int $variantId, array $expectedBomItemcodes, array $candidates): ?string
    {
        $matches = [];
        foreach ($candidates as $candidate) {
            if ($candidate->bomMatches($expectedBomItemcodes)) {
                $matches[] = $candidate->itemcode;
            }
        }

        if ($matches === []) {
            return null;
        }

        if (count($matches) > 1) {
            throw AmbiguousMatchException::forVariant($variantId, $matches);
        }

        return $matches[0];
    }
}
