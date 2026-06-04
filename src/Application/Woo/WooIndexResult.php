<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

final readonly class WooIndexResult
{
    /**
     * @param list<WooIndexRow>  $rows     Managed-itemcode-centric overzicht (één rij per AFAS-itemcode, kolommen = stores).
     * @param list<WooOrphanRow> $orphans  WC-producten waarvan de AFAS-meta NULL is óf naar een itemcode wijst die niet in onze managed-set zit.
     */
    public function __construct(
        public array $rows,
        public array $orphans,
    ) {
    }
}
