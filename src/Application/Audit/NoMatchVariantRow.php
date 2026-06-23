<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class NoMatchVariantRow
{
    /**
     * @param list<string> $verwachteBom          de itemcodes die in de compositie horen (base-items + accessoire)
     * @param ?string      $bestaandeAfasItemcode  itemcode van de AFAS-compositie met de verwachte itemcode, als die al bestaat
     * @param ?string      $exacteBomMatchItemcode itemcode van een AFAS-compositie met exact deze BOM (typisch een duplicaat)
     * @param list<string> $ontbrekendeItemcodes   itemcodes die in de verwachte BOM zitten maar niet in de bestaande AFAS-compositie ("mist")
     * @param list<string> $extraItemcodes         itemcodes die in de bestaande AFAS-compositie zitten maar niet in de verwachte BOM ("teveel")
     */
    public function __construct(
        public string $groep,
        public string $familyHead,
        public string $baseNaam,
        public string $baseAfasSku,
        public string $accessoireItemcode,
        public string $accessoireLabel,
        public array $verwachteBom,
        public string $verwachteItemcode,
        public ?string $bestaandeAfasItemcode,
        public ?string $exacteBomMatchItemcode,
        public array $ontbrekendeItemcodes,
        public array $extraItemcodes,
    ) {
    }
}
