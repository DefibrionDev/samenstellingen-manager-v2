<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Bom;

use InvalidArgumentException;

final readonly class RestoreStickers
{
    public bool $apply;
    public ?string $languageCode;
    public ?int $limit;

    public function __construct(bool $apply = false, ?string $languageCode = null, ?int $limit = null)
    {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('RestoreStickers.limit moet ≥1 zijn of null.');
        }
        $lang = $languageCode !== null ? trim($languageCode) : null;
        if ($lang === '') {
            $lang = null;
        }

        $this->apply = $apply;
        $this->languageCode = $lang;
        $this->limit = $limit;
    }
}
