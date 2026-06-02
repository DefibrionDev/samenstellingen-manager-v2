<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use Defibrion\Samenstellingen\Domain\Bom\BomLine;

interface BomComponentStripWriter
{
    /**
     * Verwijder de BOM-regel uit AFAS via PUT FbComposition met @Action=delete.
     *
     * @throws BomComponentStripFailedException bij netwerk- of AFAS-fouten.
     */
    public function apply(BomLine $line): void;
}
