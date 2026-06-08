<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

/**
 * Schrijft het `Itemcode_Parent`-vrije-veld in AFAS voor een samenstelling.
 * Gedeeld contract tussen slice 52 (family-heads → self) en slice 53
 * (non-head bases → family-head).
 */
interface ItemcodeParentWriter
{
    /**
     * @throws ItemcodeParentWriteFailedException
     */
    public function write(string $itemcode, string $parent): void;
}
