<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

/**
 * Schrijft het family-head's eigen `Itemcode_Parent`-vrije-veld in AFAS.
 * Caller bepaalt of er ge-PUT wordt of dat 't dry-run blijft.
 */
interface FamilyHeadParentWriter
{
    /**
     * @throws FamilyHeadParentWriteFailedException
     */
    public function write(string $itemcode, string $parent): void;
}
