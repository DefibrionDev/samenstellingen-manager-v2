<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixPriceMissing
{
    /**
     * @param ?list<string> $onlyForVariantItemcodes Wanneer gezet: alleen plannen
     *        voor varianten waarvan de itemcode in deze lijst staat. Leeg array
     *        betekent "geen enkele variant" (vs `null` = "alle"). Wordt gebruikt
     *        door de chained-flow van `variants:fix-missing --apply` om prijzen
     *        scoped te insertten voor net-aangemaakte varianten.
     */
    public function __construct(
        public bool $apply = false,
        public ?int $limit = null,
        public ?array $onlyForVariantItemcodes = null,
    ) {
    }
}
