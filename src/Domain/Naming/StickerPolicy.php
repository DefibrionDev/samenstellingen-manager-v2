<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Naming;

/**
 * Bepaalt welke AFAS-stickerset hoort bij een base, op basis van de taal-code:
 *
 *   NL → 81111
 *   FR → 81211
 *   DK → 81411
 *   DE → 81511
 *   anders (EN/UK/WAL/internationaal) → 81611
 *
 * Voor compound talen (`NL/FR/EN`): de eerste taal-token telt.
 */
final readonly class StickerPolicy
{
    private const STICKER_FOR_LANGUAGE = [
        'NL' => '81111',
        'FR' => '81211',
        'DK' => '81411',
        'DE' => '81511',
    ];

    private const INTERNATIONAL = '81611';

    public static function expectedSticker(string $languageCode): string
    {
        $trimmed = trim($languageCode);
        if ($trimmed === '') {
            return self::INTERNATIONAL;
        }
        $first = strtoupper(explode('/', $trimmed)[0]);

        return self::STICKER_FOR_LANGUAGE[$first] ?? self::INTERNATIONAL;
    }
}
