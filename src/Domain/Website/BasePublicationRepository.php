<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Website;

interface BasePublicationRepository
{
    /**
     * Upsert: zet de publicatie-state voor `(baseId, websiteId)`. Bij bestaande
     * row wordt `published` overschreven, bij niet-bestaande wordt een nieuwe rij
     * aangemaakt. Retourneert de huidige rij (met id).
     */
    public function setPublished(int $baseId, int $websiteId, bool $published): BasePublication;

    public function find(int $baseId, int $websiteId): ?BasePublication;

    /**
     * @return list<BasePublication>
     */
    public function findAllForBase(int $baseId): array;

    /**
     * @return list<BasePublication>
     */
    public function findAllForWebsite(int $websiteId): array;
}
