<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

/**
 * Leest de huidige waarde van geselecteerde free-field UUIDs uit AFAS voor
 * een lijst itemcodes. Wordt door `SyncPublicationsHandler` gebruikt om te
 * bepalen of een plan een no-op is (gewenste state == huidige state) en
 * dan over te slaan.
 */
interface AfasFreeFieldStateReader
{
    /**
     * Lees de huidige flag-state per itemcode. Per itemcode bevat de inner-map
     * alleen UUIDs waarvan de waarde bekend is — onbekende UUIDs ontbreken
     * (caller behandelt die als "altijd PUT'en").
     *
     * @return array<string, array<string, bool>> map itemcode → (uuid → bool)
     */
    public function readAll(): array;
}
