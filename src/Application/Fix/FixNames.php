<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixNames
{
    /**
     * @param list<string>|null $baseItemcodes Alleen drift van deze base-itemcodes fixen; null = alles.
     */
    public function __construct(
        public bool $apply = false,
        public ?int $limit = null,
        public ?array $baseItemcodes = null,
    ) {
    }
}
