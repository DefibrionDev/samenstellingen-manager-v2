<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingPlan;
use Defibrion\Samenstellingen\Infrastructure\Fix\FbCompositionVariantPayloadBuilder;
use Defibrion\Samenstellingen\Infrastructure\Fix\InMemoryVariantWriteContextLookup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FbCompositionVariantPayloadBuilderTest extends TestCase
{
    #[Test]
    public function buildsPayloadInPocShape(): void
    {
        $plan = new VariantFixMissingPlan(
            afasItemcode: '11111-60212',
            canonicalName: 'AED Pakket: 350P NL met buitenkast',
            bomItemcodes: ['10111', '81111', '60212'],
            familyHeadItemcode: '10013',
            baseAfasItemcode: '11111',
            referenceVariantItemcode: '11111-60112',
        );
        $lookup = new InMemoryVariantWriteContextLookup(
            referenceFields: [
                '11111-60112' => [
                    'grp' => '8010',
                    'cbsCode' => '90189084',
                    'productType' => 'AED pakket',
                    'subcategorie' => '350P',
                    'merknaam' => 'Heartsine',
                ],
            ],
            typeIdByItemcode: ['10111' => '2', '81111' => '7', '60212' => '7'],
        );

        $payload = (new FbCompositionVariantPayloadBuilder())->build($plan, $lookup);

        // Composite key
        self::assertSame('11111-60212', $payload['FbComposition']['Element']['@ItCd']);

        // Required header-velden
        $fields = $payload['FbComposition']['Element']['Fields'];
        self::assertSame('11111-60212', $fields['ItCd']);
        self::assertSame('AED Pakket: 350P NL met buitenkast', $fields['Ds']);
        self::assertSame('1', $fields['VaCt']);              // Explosie
        self::assertSame('8010', $fields['Grp']);             // gespiegeld
        self::assertSame('STK', $fields['BiUn']);
        self::assertSame('11111-60212', $fields['BiSaItCd']);
        self::assertSame('1', $fields['VaRc']);
        self::assertSame('50002', $fields['CrId']);
        self::assertSame('90189084', $fields['CsGc']);        // gespiegeld
        self::assertSame(0, $fields['StPrice']);

        // FF UUIDs
        self::assertSame('10013', $fields['U298663A9447D4B4D8A0BB3FBC14A2C0B']);
        self::assertTrue($fields['U4E3E32DEFB374A1BA9F8680B8C405907']);
        self::assertTrue($fields['UD77EC755E2F1404EB184A956685A7C0C']);

        // Webshop-categorisatie (slice 42) — gespiegeld uit PowerBI_Item van de referentie.
        self::assertSame('AED pakket', $fields['U5C3C0BC348244F0F97425794CE3FB4A8']); // Producttype (#01)
        self::assertSame('350P', $fields['U79C8521E4FDA2AC22FF895BD89B6D273']);        // Subcategorie (#02)
        self::assertSame('Heartsine', $fields['UE10D6C68486BDE5DE3CCC19EBE1E787B']);   // Merknaam

        // BOM-regels
        $lines = $payload['FbComposition']['Element']['Objects']['FbCompositionPart']['Element'];
        self::assertCount(3, $lines);
        self::assertSame(['VaIt' => 'Art', 'ItCd' => '10111', 'QuUn' => 1, 'Qu' => 1, 'PrSe' => 10], $lines[0]['Fields']);
        self::assertSame(['VaIt' => 'Sam', 'ItCd' => '81111', 'QuUn' => 1, 'Qu' => 1, 'PrSe' => 20], $lines[1]['Fields']);
        self::assertSame(['VaIt' => 'Sam', 'ItCd' => '60212', 'QuUn' => 1, 'Qu' => 1, 'PrSe' => 30], $lines[2]['Fields']);
    }
}
