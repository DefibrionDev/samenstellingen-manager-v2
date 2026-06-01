<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Website;

interface WebsiteRepository
{
    /**
     * @throws WebsiteAlreadyExistsException wanneer een website met deze naam al bestaat.
     */
    public function save(Website $website): Website;

    public function findByName(string $name): ?Website;

    public function findById(int $id): ?Website;

    /**
     * @return list<Website>
     */
    public function findAll(): array;

    /**
     * Verwijder een website. Cascade ruimt base_publications op.
     * Idempotent: onbekende naam is no-op.
     */
    public function delete(string $name): void;
}
