<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

interface BomComponentRestoreWriter
{
    /**
     * Voeg een BOM-regel toe in AFAS via PUT FbComposition met `@Action=insert`.
     *
     * @throws BomComponentRestoreFailedException bij netwerk- of AFAS-fouten.
     */
    public function apply(BomComponentRestorePlan $plan): void;
}
