<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

final readonly class AfasSamenstellingLookup
{
    public function __construct(private AfasSamenstellingenRepository $repository)
    {
    }

    /**
     * Zoek alle canonical samenstellingen waarvan de BOM het opgegeven article-itemcode
     * bevat en GEEN van de geregistreerde accessoire-itemcodes. Dat zijn de base-kandidaten.
     *
     * @param list<string> $accessoireItemcodes Itemcodes van geregistreerde accessoires.
     *                                          Een samenstelling die er één bevat is geen base.
     * @return list<AfasSamenstelling>
     */
    public function findCanonicalBasesContaining(string $articleCode, array $accessoireItemcodes): array
    {
        $blocked = [];
        foreach ($accessoireItemcodes as $code) {
            $blocked[$code] = true;
        }

        $match = [];
        foreach ($this->repository->findAllCanonical() as $samenstelling) {
            if (!in_array($articleCode, $samenstelling->bomItemcodes, true)) {
                continue;
            }
            if ($this->bomContainsBlockedCode($samenstelling->bomItemcodes, $blocked)) {
                continue;
            }
            $match[] = $samenstelling;
        }

        return $match;
    }

    /**
     * @param list<string> $bom
     * @param array<string, true> $blocked
     */
    private function bomContainsBlockedCode(array $bom, array $blocked): bool
    {
        foreach ($bom as $code) {
            if (isset($blocked[$code])) {
                return true;
            }
        }

        return false;
    }
}
