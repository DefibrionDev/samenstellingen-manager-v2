<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Fix;

use Defibrion\Samenstellingen\Infrastructure\Fix\FbCompositionEnumResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FbCompositionEnumResolverTest extends TestCase
{
    #[Test]
    public function buildsDescriptionToIdMapPerField(): void
    {
        $payload = [
            'fields' => [
                [
                    'fieldId' => 'U-PRODUCT-TYPE',
                    'values' => [
                        ['id' => '08', 'description' => 'AED pakket'],
                        ['id' => '12', 'description' => 'Toebehoren'],
                    ],
                ],
                [
                    'fieldId' => 'U-SUB',
                    'values' => [
                        ['id' => 'C1A', 'description' => 'C1A'],
                    ],
                ],
                // Veld zonder values → genegeerd.
                ['fieldId' => 'U-EMPTY'],
            ],
        ];

        $maps = FbCompositionEnumResolver::buildMaps($payload);

        self::assertSame('08', $maps['U-PRODUCT-TYPE']['AED pakket']);
        self::assertSame('12', $maps['U-PRODUCT-TYPE']['Toebehoren']);
        self::assertSame('C1A', $maps['U-SUB']['C1A']);
        self::assertArrayNotHasKey('U-EMPTY', $maps);
    }

    #[Test]
    public function buildMapsToleratesMalformedPayload(): void
    {
        self::assertSame([], FbCompositionEnumResolver::buildMaps([]));
        self::assertSame([], FbCompositionEnumResolver::buildMaps(['fields' => 'nope']));
    }
}
