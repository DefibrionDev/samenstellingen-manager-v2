<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

interface VariantFixMissingWriter
{
    /**
     * Maak de variant aan in AFAS via FbComposition (POST), inclusief BOM-regels.
     *
     * @throws VariantFixMissingFailedException bij netwerk- of AFAS-fouten.
     */
    public function apply(VariantFixMissingPlan $plan): void;
}
