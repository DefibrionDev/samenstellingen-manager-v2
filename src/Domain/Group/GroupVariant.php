<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Group;

final readonly class GroupVariant
{
    public function __construct(
        public ?int $id,
        public int $baseId,
        public string $baseName,
        public ?string $accessoireItemcode,
        public ?string $accessoireLabel,
        public ?string $afasSamenstellingItemcode,
        public ?string $afasStatus = null,
    ) {
    }

    public function isBaseOnly(): bool
    {
        return $this->accessoireItemcode === null;
    }
}
