<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Website\BasePublication;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;

final class InMemoryBasePublicationRepository implements BasePublicationRepository
{
    private int $nextId = 1;

    /** @var array<int, BasePublication> */
    private array $byId = [];

    public function setPublished(int $baseId, int $websiteId, bool $published): BasePublication
    {
        foreach ($this->byId as $id => $existing) {
            if ($existing->baseId === $baseId && $existing->websiteId === $websiteId) {
                $updated = new BasePublication($id, $baseId, $websiteId, $published);
                $this->byId[$id] = $updated;

                return $updated;
            }
        }

        $persisted = new BasePublication($this->nextId, $baseId, $websiteId, $published);
        ++$this->nextId;
        $this->byId[$persisted->id ?? 0] = $persisted;

        return $persisted;
    }

    public function find(int $baseId, int $websiteId): ?BasePublication
    {
        foreach ($this->byId as $pub) {
            if ($pub->baseId === $baseId && $pub->websiteId === $websiteId) {
                return $pub;
            }
        }

        return null;
    }

    public function findAllForBase(int $baseId): array
    {
        $result = [];
        foreach ($this->byId as $pub) {
            if ($pub->baseId === $baseId) {
                $result[] = $pub;
            }
        }

        return $result;
    }

    public function findAllForWebsite(int $websiteId): array
    {
        $result = [];
        foreach ($this->byId as $pub) {
            if ($pub->websiteId === $websiteId) {
                $result[] = $pub;
            }
        }

        return $result;
    }
}
