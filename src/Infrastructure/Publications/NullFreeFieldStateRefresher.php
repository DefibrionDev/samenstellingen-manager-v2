<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Publications;

use Defibrion\Samenstellingen\Application\Publications\FreeFieldStateRefresher;

/**
 * No-op refresher: gebruikt wanneer er geen AFAS-credentials zijn (de
 * publicatie-state kan dan niet live opgehaald worden) en in tests.
 */
final readonly class NullFreeFieldStateRefresher implements FreeFieldStateRefresher
{
    public function refresh(): void
    {
    }
}
