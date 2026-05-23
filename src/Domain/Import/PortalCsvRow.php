<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Import;

final readonly class PortalCsvRow
{
    public function __construct(
        public string $code,
        public string $groep,
        public string $item,
        public string $merknaam,
        public string $taal = '',
    ) {
    }

    public function hasGroep(): bool
    {
        return $this->groep !== '';
    }

    public function languageCode(): ?string
    {
        $trimmed = trim($this->taal);

        return $trimmed === '' ? null : $trimmed;
    }
}
