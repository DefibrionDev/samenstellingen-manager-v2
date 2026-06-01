<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Website;

/**
 * Publicatie-state voor een base op een specifieke website. Accessoire-
 * varianten van die base erven impliciet de waarde. Zie PLAN.md §25.
 */
final readonly class BasePublication
{
    public function __construct(
        public ?int $id,
        public int $baseId,
        public int $websiteId,
        public bool $published,
    ) {
    }
}
