<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Group\GroupVariant;

final readonly class SyncSummary
{
    /**
     * @param list<array{variant: GroupVariant, afasItemcode: string}> $matched
     * @param list<GroupVariant>                                       $notMatched
     */
    public function __construct(
        public string $familyHeadItemcode,
        public int $afasSamenstellingenCount,
        public array $matched,
        public array $notMatched,
    ) {
    }

    public function matchCount(): int
    {
        return count($this->matched);
    }

    public function noMatchCount(): int
    {
        return count($this->notMatched);
    }
}
