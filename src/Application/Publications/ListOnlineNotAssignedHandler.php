<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Publications;

/**
 * Read-only audit: itemcodes×website die in AFAS online staan (Sync/Tonen=true)
 * maar in de tool niet aan die website zijn toegekend. Hergebruikt de
 * vergelijk-logica van {@see SyncPublicationsHandler} (dry-run) — daar valt de
 * "online maar niet toegekend"-set al uit. De handler wordt gewired met de
 * lokale snapshot-reader, dus draait zonder live AFAS-calls. Zie PLAN.md §12.
 */
final readonly class ListOnlineNotAssignedHandler
{
    public function __construct(private SyncPublicationsHandler $sync)
    {
    }

    /**
     * @return list<OnlineNotAssignedRow>
     */
    public function __invoke(): array
    {
        return ($this->sync)(new SyncPublications(apply: false))->onlineNotAssigned;
    }
}
