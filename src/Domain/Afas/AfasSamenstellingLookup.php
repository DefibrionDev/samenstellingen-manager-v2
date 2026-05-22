<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

final readonly class AfasSamenstellingLookup
{
    public function __construct(private AfasSamenstellingenRepository $repository)
    {
    }

    /**
     * Zoek alle canonical base-only samenstellingen (geen `-` in itemcode, geen duplicate)
     * waarvan de BOM het opgegeven article bevat.
     *
     * @return list<AfasSamenstelling>
     */
    public function findCanonicalBaseOnlyContaining(string $articleCode): array
    {
        $match = [];
        foreach ($this->repository->findAllCanonical() as $samenstelling) {
            if (!$samenstelling->isBaseOnly()) {
                continue;
            }
            if (in_array($articleCode, $samenstelling->bomItemcodes, true)) {
                $match[] = $samenstelling;
            }
        }

        return $match;
    }
}
