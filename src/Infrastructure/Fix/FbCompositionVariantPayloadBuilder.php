<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingPlan;
use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextLookup;

/**
 * Bouwt het FbComposition POST-payload zoals geverifieerd in PoC 39.0.
 * Pure functie — geen netwerk, geen state — zodat de payload-shape per
 * unit-test gecontroleerd kan worden zonder live AFAS. Zie PLAN.md §20.
 */
final readonly class FbCompositionVariantPayloadBuilder
{
    // Free-field UUID's uit AFAS (zelfde als afas-connector-tools).
    private const FF_PARENT = 'U298663A9447D4B4D8A0BB3FBC14A2C0B';
    private const FF_SYNC = 'U4E3E32DEFB374A1BA9F8680B8C405907';
    private const FF_TONEN = 'UD77EC755E2F1404EB184A956685A7C0C';

    // Webshop-categorisatie (slice 42) — UUIDs uit metainfo/update/FbComposition.
    private const FF_PRODUCT_TYPE = 'U5C3C0BC348244F0F97425794CE3FB4A8';
    private const FF_SUBCATEGORIE = 'U79C8521E4FDA2AC22FF895BD89B6D273';
    private const FF_MERKNAAM = 'UE10D6C68486BDE5DE3CCC19EBE1E787B';

    // Constanten voor onze AED-pakketten — bewust hardcoded:
    // alle AED-samenstellingen zijn Explosie, BTW 21% NL, Defibrion is
    // de eigen inkooprelatie en pakketten zijn virtueel (Verrekenprijs 0).
    private const VA_CT_EXPLOSIE = '1';
    private const BI_UN_STUK = 'STK';
    private const VA_RC_BTW_21 = '1';
    private const CR_ID_DEFIBRION = '50002';
    private const ST_PRICE_VIRTUEEL = 0;

    /**
     * @return array<string, mixed>
     */
    public function build(VariantFixMissingPlan $plan, VariantWriteContextLookup $lookup): array
    {
        $reference = $lookup->lookupReferenceFields($plan->referenceVariantItemcode);

        $lines = [];
        foreach ($plan->bomItemcodes as $index => $bomCode) {
            $lines[] = [
                'Fields' => [
                    'VaIt' => $lookup->lookupBomItemType($bomCode),
                    'ItCd' => $bomCode,
                    'QuUn' => 1,
                    'Qu' => 1,
                    'PrSe' => ($index + 1) * 10,
                ],
            ];
        }

        $fields = [
            'ItCd' => $plan->afasItemcode,
            'Ds' => $plan->canonicalName,
            self::FF_PARENT => $plan->familyHeadItemcode,
            self::FF_SYNC => true,
            self::FF_TONEN => true,
            'VaCt' => self::VA_CT_EXPLOSIE,
            'Grp' => $reference['grp'],
            'BiUn' => self::BI_UN_STUK,
            'BiSaItCd' => $plan->afasItemcode,
            'VaRc' => self::VA_RC_BTW_21,
            'CrId' => self::CR_ID_DEFIBRION,
            'CsGc' => $reference['cbsCode'],
            'StPrice' => self::ST_PRICE_VIRTUEEL,
        ];

        // Webshop-categorisatie alleen meesturen als de referentie waardes had —
        // anders zou AFAS de bestaande waarden op een hypothetische update kunnen
        // legen. Voor POST is dit grotendeels academisch maar consistent.
        if ($reference['productType'] !== '') {
            $fields[self::FF_PRODUCT_TYPE] = $reference['productType'];
        }
        if ($reference['subcategorie'] !== '') {
            $fields[self::FF_SUBCATEGORIE] = $reference['subcategorie'];
        }
        if ($reference['merknaam'] !== '') {
            $fields[self::FF_MERKNAAM] = $reference['merknaam'];
        }

        return [
            'FbComposition' => [
                'Element' => [
                    '@ItCd' => $plan->afasItemcode,
                    'Fields' => $fields,
                    'Objects' => [
                        'FbCompositionPart' => [
                            'Element' => $lines,
                        ],
                    ],
                ],
            ],
        ];
    }
}
