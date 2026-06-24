<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

/**
 * Lokale snapshot van de AFAS free-field publicatie-state. Leest (via de
 * {@see AfasFreeFieldStateReader}-interface) én vervangt de snapshot in één
 * keer. De snapshot wordt bij elke `afas:pull` ververst vanuit de live
 * AFAS-state, zodat de "online maar niet toegekend"-audit lokaal kan lezen.
 */
interface AfasFreeFieldStateRepository extends AfasFreeFieldStateReader
{
    /**
     * Vervang de hele snapshot atomisch. Numerieke itemcode-keys worden door PHP
     * naar int gecast (vandaar int|string), net als bij {@see AfasFreeFieldStateReader}.
     *
     * @param array<int|string, array<string, bool>> $state map itemcode → (uuid → bool)
     */
    public function replaceSnapshot(array $state): void;
}
