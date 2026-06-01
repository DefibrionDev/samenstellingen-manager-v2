<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Website;

use InvalidArgumentException;

/**
 * Een website is een AFAS-bestemming (bv. "Reseller NL", "Reseller FR") met
 * z'n eigen vrije-veld-paar voor `Sync_*` (zichtbaar voor reseller) en
 * `Tonen_*` (synchroniseren naar reseller). Zie PLAN.md §25.
 */
final readonly class Website
{
    public ?int $id;
    public string $name;
    public string $ffSyncUuid;
    public string $ffTonenUuid;

    public function __construct(?int $id, string $name, string $ffSyncUuid, string $ffTonenUuid)
    {
        $name = trim($name);
        $ffSyncUuid = trim($ffSyncUuid);
        $ffTonenUuid = trim($ffTonenUuid);

        if ($name === '') {
            throw new InvalidArgumentException('Website-naam mag niet leeg zijn.');
        }
        if ($ffSyncUuid === '') {
            throw new InvalidArgumentException('Website-ff_sync_uuid mag niet leeg zijn.');
        }
        if ($ffTonenUuid === '') {
            throw new InvalidArgumentException('Website-ff_tonen_uuid mag niet leeg zijn.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->ffSyncUuid = $ffSyncUuid;
        $this->ffTonenUuid = $ffTonenUuid;
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name, $this->ffSyncUuid, $this->ffTonenUuid);
    }
}
