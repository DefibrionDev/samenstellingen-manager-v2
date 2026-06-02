<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use InvalidArgumentException;

/**
 * Eén te-herstellen BOM-regel: AFAS-samenstelling X krijgt onderdeel Y terug
 * met VaIt + PrSe. PrSe wordt op handler-niveau bepaald (`max + 10`).
 */
final readonly class BomComponentRestorePlan
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
        if ($s === '' || $b === '' || $v === '') {
            throw new InvalidArgumentException('BomComponentRestorePlan velden mogen niet leeg zijn.');
        }

        $this->samenstellingItemcode = $s;
        $this->bomItemcode = $b;
        $this->vaIt = $v;
        $this->prSe = $prSe;
    }
}
