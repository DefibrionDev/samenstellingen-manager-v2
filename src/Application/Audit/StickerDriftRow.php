<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class StickerDriftRow
{
    /**
     * @param list<string> $actualStickers BOM-codes die met '81' beginnen (kan leeg zijn)
     */
    public function __construct(
        public string $groupName,
        public string $familyHeadItemcode,
        public string $baseName,
        public string $baseAfasItemcode,
        public string $languageCode,
        public string $expectedSticker,
        public array $actualStickers,
    ) {
    }
}
