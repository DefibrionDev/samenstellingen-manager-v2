<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

/**
 * Classificatie van een producttype-probleem op een samenstelling.
 * Zie PLAN-AFAS.md §35.
 */
enum ProductTypeIssueType: string
{
    /** Base-samenstelling met 01 óf 02 leeg — alleen door de gebruiker in AFAS te vullen. */
    case BaseEmpty = 'base-leeg';

    /** Accessoire-variant wijkt af van een gevulde base — auto-fixbaar via CLI. */
    case VariantFixable = 'variant-fixbaar';

    /** Accessoire-variant wijkt af, maar de base is zelf leeg — eerst base vullen. */
    case VariantBlocked = 'variant-geblokkeerd';
}
