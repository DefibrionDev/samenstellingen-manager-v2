<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Woo;

use Defibrion\Samenstellingen\Infrastructure\Woo\WooMetaDataExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WooMetaDataExtractorTest extends TestCase
{
    #[Test]
    public function returnsStringValueWhenKeyPresent(): void
    {
        $meta = [
            ['id' => 1, 'key' => '_other', 'value' => 'irrelevant'],
            ['id' => 2, 'key' => '_afas_itemcode', 'value' => '21011'],
        ];

        self::assertSame('21011', WooMetaDataExtractor::extract($meta, '_afas_itemcode'));
    }

    #[Test]
    public function respectsCustomMetaKey(): void
    {
        $meta = [['id' => 1, 'key' => 'afas_item_nummer', 'value' => '11111-60110']];

        self::assertSame('11111-60110', WooMetaDataExtractor::extract($meta, 'afas_item_nummer'));
    }

    #[Test]
    public function returnsNullWhenKeyAbsent(): void
    {
        $meta = [['id' => 1, 'key' => '_other', 'value' => 'x']];

        self::assertNull(WooMetaDataExtractor::extract($meta, '_afas_itemcode'));
    }

    #[Test]
    public function returnsNullWhenMetaArrayEmpty(): void
    {
        self::assertNull(WooMetaDataExtractor::extract([], '_afas_itemcode'));
    }

    #[Test]
    public function returnsNullForArrayValue(): void
    {
        // ACF + sommige plugins serializeren arrays in meta_data — we accepteren alleen scalar strings.
        $meta = [['id' => 1, 'key' => '_afas_itemcode', 'value' => ['nested' => '21011']]];

        self::assertNull(WooMetaDataExtractor::extract($meta, '_afas_itemcode'));
    }

    #[Test]
    public function returnsNullForEmptyStringValue(): void
    {
        $meta = [['id' => 1, 'key' => '_afas_itemcode', 'value' => '']];

        self::assertNull(WooMetaDataExtractor::extract($meta, '_afas_itemcode'));
    }

    #[Test]
    public function coercesIntegerValueToString(): void
    {
        // WC kan numerieke meta-values als int teruggeven (b.v. afas_item_nummer = 12345).
        $meta = [['id' => 1, 'key' => '_afas_itemcode', 'value' => 12345]];

        self::assertSame('12345', WooMetaDataExtractor::extract($meta, '_afas_itemcode'));
    }

    #[Test]
    public function ignoresMalformedEntriesWithoutKey(): void
    {
        $meta = [
            ['id' => 1, 'value' => 'no-key-here'],
            ['id' => 2, 'key' => '_afas_itemcode', 'value' => '21011'],
        ];

        self::assertSame('21011', WooMetaDataExtractor::extract($meta, '_afas_itemcode'));
    }
}
