<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use InvalidArgumentException;

/**
 * Eén te-creëren ontbrekende variant in AFAS: nieuw FbComposition-record met
 * canonical naam + BOM, gespiegeld op een referentie-variant in dezelfde groep
 * (voor Grp / CsGc — die zijn groep-specifiek). Zie PLAN.md §20.
 *
 * @phpstan-type BomItemcodes list<non-empty-string>
 */
final readonly class VariantFixMissingPlan
{
    public string $afasItemcode;
    public string $canonicalName;
    /** @var list<string> */
    public array $bomItemcodes;
    public string $familyHeadItemcode;
    public string $baseAfasItemcode;
    public string $referenceVariantItemcode;

    /**
     * @param list<string> $bomItemcodes Geordende lijst BOM-itemcodes — eerste item kop, daarna toevoegingen.
     */
    public function __construct(
        string $afasItemcode,
        string $canonicalName,
        array $bomItemcodes,
        string $familyHeadItemcode,
        string $baseAfasItemcode,
        string $referenceVariantItemcode,
    ) {
        $afasItemcode = trim($afasItemcode);
        if ($afasItemcode === '') {
            throw new InvalidArgumentException('VariantFixMissingPlan.afasItemcode mag niet leeg zijn.');
        }
        $canonicalName = trim($canonicalName);
        if ($canonicalName === '') {
            throw new InvalidArgumentException('VariantFixMissingPlan.canonicalName mag niet leeg zijn.');
        }
        if ($bomItemcodes === []) {
            throw new InvalidArgumentException('VariantFixMissingPlan.bomItemcodes mag niet leeg zijn.');
        }
        $familyHeadItemcode = trim($familyHeadItemcode);
        if ($familyHeadItemcode === '') {
            throw new InvalidArgumentException('VariantFixMissingPlan.familyHeadItemcode mag niet leeg zijn.');
        }
        $baseAfasItemcode = trim($baseAfasItemcode);
        if ($baseAfasItemcode === '') {
            throw new InvalidArgumentException('VariantFixMissingPlan.baseAfasItemcode mag niet leeg zijn.');
        }
        $referenceVariantItemcode = trim($referenceVariantItemcode);
        if ($referenceVariantItemcode === '') {
            throw new InvalidArgumentException('VariantFixMissingPlan.referenceVariantItemcode mag niet leeg zijn.');
        }

        $this->afasItemcode = $afasItemcode;
        $this->canonicalName = $canonicalName;
        $this->bomItemcodes = $bomItemcodes;
        $this->familyHeadItemcode = $familyHeadItemcode;
        $this->baseAfasItemcode = $baseAfasItemcode;
        $this->referenceVariantItemcode = $referenceVariantItemcode;
    }
}
