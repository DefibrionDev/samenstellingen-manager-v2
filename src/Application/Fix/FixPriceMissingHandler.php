<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\AuditPrices;
use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;

/**
 * Detecteert ontbrekende variant-prijzen via PriceAuditHandler (status=missing)
 * en POST'ed nieuwe prijs-rijen via FbSalesPrice.
 *
 * `targetCents = baseCents + accessoires.delta_cents` — zelfde toeslag-formule
 * als de drift-fix.
 *
 * Begindatum: van de **base**-prijs in dezelfde (prijslijst, staffel) — de
 * variant-rij bestaat nog niet, dus we kunnen 'm niet uit de variant lookuppen.
 *
 * Skipt variants die niet als artikel in AFAS bestaan (POST faalt anders met
 * "Itemcode bestaat niet"). Variant-artikelen aanmaken zit in slice 13.
 */
final readonly class FixPriceMissingHandler
{
    public function __construct(
        private PriceAuditHandler $audit,
        private BeginDateLookup $beginDateLookup,
        private AfasArticleRepository $articles,
        private PriceFixWriter $writer,
    ) {
    }

    public function __invoke(FixPriceMissing $command): FixPriceMissingResult
    {
        $missingRows = array_values(array_filter(
            ($this->audit)(new AuditPrices()),
            static fn ($row): bool => $row->status === 'missing',
        ));

        if ($command->onlyForVariantItemcodes !== null) {
            $allowed = array_flip($command->onlyForVariantItemcodes);
            $missingRows = array_values(array_filter(
                $missingRows,
                static fn ($row): bool => isset($allowed[$row->variantAfasItemcode]),
            ));
        }

        $plans = [];
        $skippedNoArticle = [];
        foreach ($missingRows as $row) {
            if ($row->basePrijsCents === null) {
                continue;
            }
            if ($this->articles->findByItemcode($row->variantAfasItemcode) === null) {
                $skippedNoArticle[] = $row->variantAfasItemcode;
                continue;
            }
            // Begindatum van de base in deze (prijslijst, staffel) — variant bestaat nog niet.
            $beginDate = $this->beginDateLookup->find($row->baseAfasItemcode, $row->prijslijstId, $row->staffelAantal);
            if ($beginDate === null) {
                continue;
            }
            $plans[] = new PriceFixPlan(
                $row->variantAfasItemcode,
                $row->prijslijstId,
                $row->staffelAantal,
                0,
                $row->basePrijsCents + $row->expectedDeltaCents,
                $beginDate,
            );
            if ($command->limit !== null && count($plans) >= $command->limit) {
                break;
            }
        }

        $applied = 0;
        $failures = [];
        if ($command->apply) {
            foreach ($plans as $plan) {
                try {
                    $this->writer->insert($plan);
                    $applied++;
                } catch (PriceFixFailedException $e) {
                    $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
                }
            }
        }

        return new FixPriceMissingResult($plans, $applied, $failures, array_values(array_unique($skippedNoArticle)));
    }
}
