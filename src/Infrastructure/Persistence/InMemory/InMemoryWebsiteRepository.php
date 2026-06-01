<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Domain\Website\WebsiteAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;

final class InMemoryWebsiteRepository implements WebsiteRepository
{
    private int $nextId = 1;

    /** @var array<int, Website> */
    private array $byId = [];

    /** @var array<string, int> */
    private array $idByName = [];

    public function save(Website $website): Website
    {
        if (isset($this->idByName[$website->name])) {
            throw WebsiteAlreadyExistsException::forName($website->name);
        }
        $persisted = $website->withId($this->nextId);
        ++$this->nextId;
        $this->byId[$persisted->id ?? 0] = $persisted;
        $this->idByName[$persisted->name] = $persisted->id ?? 0;

        return $persisted;
    }

    public function findByName(string $name): ?Website
    {
        $id = $this->idByName[$name] ?? null;

        return $id !== null ? ($this->byId[$id] ?? null) : null;
    }

    public function findById(int $id): ?Website
    {
        return $this->byId[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->byId);
    }

    public function delete(string $name): void
    {
        $id = $this->idByName[$name] ?? null;
        if ($id === null) {
            return;
        }
        unset($this->byId[$id], $this->idByName[$name]);
    }
}
