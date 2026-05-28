<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Domain\Naming;

use Defibrion\Samenstellingen\Domain\Naming\StickerPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StickerPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function languageToStickerProvider(): iterable
    {
        yield 'NL pur'              => ['NL', '81111'];
        yield 'FR pur'              => ['FR', '81211'];
        yield 'DK pur'              => ['DK', '81411'];
        yield 'DE pur'              => ['DE', '81511'];
        yield 'EN → internationaal' => ['EN', '81611'];
        yield 'UK → internationaal' => ['UK', '81611'];
        yield 'WAL → internationaal' => ['WAL', '81611'];
        yield 'NL/EN compound → NL' => ['NL/EN', '81111'];
        yield 'NL/FR/EN → NL'       => ['NL/FR/EN', '81111'];
        yield 'EN/NL → EN = inter.' => ['EN/NL', '81611'];
        yield 'DE/EN compound → DE' => ['DE/EN', '81511'];
        yield 'lege → internationaal' => ['', '81611'];
        yield 'kleine letters'      => ['nl', '81111'];
    }

    #[Test]
    #[DataProvider('languageToStickerProvider')]
    public function expectedStickerForLanguage(string $language, string $expected): void
    {
        self::assertSame($expected, StickerPolicy::expectedSticker($language));
    }
}
