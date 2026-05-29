<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Afas\PullAfasSamenstellingen;
use Defibrion\Samenstellingen\Application\Afas\PullAfasSamenstellingenHandler;
use Defibrion\Samenstellingen\Application\Fix\VariantSnapshotRefresher;

/**
 * HTTP-implementatie — roept de volledige `afas:pull` aan zodat
 * `afas_articles`, `afas_samenstellingen` en `afas_prijzen` allemaal de
 * net-aangemaakte varianten kennen. Niet targeted (~30s per run) maar
 * het volstaat voor typische `--limit=N`-rollouts.
 */
final readonly class PullAfasVariantSnapshotRefresher implements VariantSnapshotRefresher
{
    public function __construct(private PullAfasSamenstellingenHandler $pullHandler)
    {
    }

    public function refresh(): void
    {
        ($this->pullHandler)(new PullAfasSamenstellingen());
    }
}
