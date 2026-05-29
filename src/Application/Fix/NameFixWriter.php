<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

interface NameFixWriter
{
    /**
     * Schrijf de target-naam naar AFAS via FbItemArticle (PUT) op het Ds-veld.
     *
     * @throws NameFixFailedException bij netwerk- of AFAS-fouten.
     */
    public function apply(NameFixPlan $plan): void;
}
