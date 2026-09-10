<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use Defibrion\Samenstellingen\Domain\Bom\BomLine;

final readonly class StripBomComponentResult
{
    /**
     * @param list<BomLine>                                $plannedLines    Te-strippen AFAS-regels.
     * @param int                                          $toolRowsDeleted Aantal `group_base_items`-rijen verwijderd (0 bij dry-run).
     * @param int                                          $appliedCount    Aantal AFAS-deletes geslaagd (0 bij dry-run).
     * @param list<array{line: BomLine, error: string}>    $failures        Per AFAS-delete die faalde.
     * @param int                                          $skippedCount    AFAS-regels buiten de `onlyWith`-scope gelaten.
     * @param list<BomLine>                                $unsafeLines     Regels waarvan de PrSe niet uniek is in hun samenstelling: niet gestript (AFAS matcht de delete op PrSe alléén).
     */
    public function __construct(
        public array $plannedLines,
        public int $toolRowsDeleted,
        public int $appliedCount,
        public array $failures,
        public int $skippedCount = 0,
        public array $unsafeLines = [],
    ) {
    }
}
