<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Bom;

use InvalidArgumentException;

/**
 * Eén regel uit een AFAS-samenstelling BOM: welk onderdeel (`bomItemcode`)
 * met welk type (`vaIt`) op welke presentatievolgorde (`prSe`) binnen welke
 * samenstelling (`samenstellingItemcode`). Dient als composite-key voor
 * `FbCompositionPart` PUT-mutaties.
 */
final readonly class BomLine
{
    public string $samenstellingItemcode;
    public string $bomItemcode;
    public string $vaIt;
    public int $prSe;

    public function __construct(string $samenstellingItemcode, string $bomItemcode, string $vaIt, int $prSe)
    {
        $s = trim($samenstellingItemcode);
        $b = trim($bomItemcode);
        $v = trim($vaIt);
        if ($s === '') {
            throw new InvalidArgumentException('BomLine.samenstellingItemcode mag niet leeg zijn.');
        }
        if ($b === '') {
            throw new InvalidArgumentException('BomLine.bomItemcode mag niet leeg zijn.');
        }
        if ($v === '') {
            throw new InvalidArgumentException('BomLine.vaIt mag niet leeg zijn.');
        }

        $this->samenstellingItemcode = $s;
        $this->bomItemcode = $b;
        $this->vaIt = $v;
        $this->prSe = $prSe;
    }
}
