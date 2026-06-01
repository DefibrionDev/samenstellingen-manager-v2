<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

interface PublicationSyncWriter
{
    /**
     * PUT FbComposition met @ItCd + Fields die de free-field flags zetten.
     *
     * @throws PublicationSyncFailedException bij netwerk- of AFAS-fouten.
     */
    public function apply(PublicationSyncPlan $plan): void;
}
