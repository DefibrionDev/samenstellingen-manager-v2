<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

/**
 * Ververst de lokale free-field-state-snapshot vanuit de live AFAS-state.
 * Aangeroepen tijdens `afas:pull`. Een no-op-implementatie ({@see
 * \Defibrion\Samenstellingen\Infrastructure\Publications\NullFreeFieldStateRefresher})
 * wordt gebruikt wanneer er geen AFAS-credentials zijn (en in tests).
 */
interface FreeFieldStateRefresher
{
    public function refresh(): void;
}
