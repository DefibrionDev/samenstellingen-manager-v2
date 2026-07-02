<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class NoMatchVariantRow
{
    public const ACTIE_AANMAAKBAAR = 'aanmaakbaar';
    public const ACTIE_BESTAAT_AL = 'bestaat_al_afwijkende_bom';
    public const ACTIE_BOM_ELDERS = 'bom_bestaat_elders';
    public const ACTIE_BASE_NIET_GEMATCHT = 'base_niet_gematcht';

    /**
     * @param list<string> $verwachteBom          de itemcodes die in de compositie horen (base-items + accessoire)
     * @param ?string      $bestaandeAfasItemcode  itemcode van de AFAS-compositie met de verwachte itemcode, als die al bestaat
     * @param ?string      $exacteBomMatchItemcode itemcode van een AFAS-compositie met exact deze BOM (typisch een duplicaat)
     * @param list<string> $ontbrekendeItemcodes   itemcodes die in de verwachte BOM zitten maar niet in de bestaande AFAS-compositie ("mist")
     * @param list<string> $extraItemcodes         itemcodes die in de bestaande AFAS-compositie zitten maar niet in de verwachte BOM ("teveel")
     * @param self::ACTIE_* $actie                  wat er met deze rij moet gebeuren:
     *   - aanmaakbaar: `variants:fix-missing` kan de compositie veilig POSTen;
     *   - bestaat_al_afwijkende_bom: verwachte itemcode bestaat al in AFAS maar met andere BOM → BOM onderzoeken/fixen;
     *   - bom_bestaat_elders: exact deze BOM bestaat al onder een ander itemcode → koppelen/hernoemen i.p.v. aanmaken;
     *   - base_niet_gematcht: de base zelf heeft geen AFAS-match → eerst de base oplossen.
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
        public string $actie,
    ) {
    }
}
