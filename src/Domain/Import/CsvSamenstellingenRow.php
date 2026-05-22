<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Import;

final readonly class CsvSamenstellingenRow
{
    public function __construct(
        public string $samenstellingItemcode,
        public string $samenstellingNaam,
        public string $aedArticle,
        public string $aedArticleNaam,
    ) {
    }

    public function isBaseOnly(): bool
    {
        return !str_contains($this->samenstellingItemcode, '-');
    }

    public function extractAccessoireItemcode(): ?string
    {
        if ($this->isBaseOnly()) {
            return null;
        }
        $parts = explode('-', $this->samenstellingItemcode, 2);

        return $parts[1] === '' ? null : $parts[1];
    }
}
