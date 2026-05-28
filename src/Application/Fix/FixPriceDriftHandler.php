<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

use Defibrion\Samenstellingen\Application\Audit\AuditPrices;
use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;

/**
 * Detecteert drift via PriceAuditHandler en zet ze om naar PriceFixPlans.
 * In dry-run-modus alleen plannen; bij apply=true echt schrijven via writer.
 *
 * `targetCents = baseCents + accessoires.delta_cents` — letterlijk wat audit
 * als "verwacht" rapporteert.
 *
 * Begindatum: opgehaald uit de bestaande variant-prijs-rij in dezelfde
 * (prijslijst, staffel). Behouden voorkomt prijshistorie-corruptie.
 *
 * Skipt `inconsistent-staffel`-rijen (variant heeft staffel die base mist —
 * auto-fix onveilig).
 */
final readonly class FixPriceDriftHandler
{
    public function __construct(
        private PriceAuditHandler $audit,
        private BeginDateLookup $beginDateLookup,
        private PriceFixWriter $writer,
    ) {
    }

    public function __invoke(FixPriceDrift $command): FixPriceDriftResult
    {
        $driftRows = array_values(array_filter(
            ($this->audit)(new AuditPrices()),
            static fn ($row): bool => $row->status === 'toeslag-drift',
        ));

        $plans = [];
        foreach ($driftRows as $row) {
            if ($row->basePrijsCents === null) {
                continue;
            }
            $target = $row->basePrijsCents + $row->expectedDeltaCents;
            if ($row->variantPrijsCents !== null && $target === $row->variantPrijsCents) {
                continue; // niets te fixen
            }
            $beginDate = $this->beginDateLookup->find($row->variantAfasItemcode, $row->prijslijstId, $row->staffelAantal);
            if ($beginDate === null) {
                continue; // geen bestaande variant-rij gevonden — onverwacht, skip
            }
            $plans[] = new PriceFixPlan(
                $row->variantAfasItemcode,
                $row->prijslijstId,
                $row->staffelAantal,
                $row->variantPrijsCents ?? 0,
                $target,
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
                    $this->writer->apply($plan);
                    $applied++;
                } catch (PriceFixFailedException $e) {
                    $failures[] = ['plan' => $plan, 'error' => $e->getMessage()];
                }
            }
        }

        return new FixPriceDriftResult($plans, $applied, $failures);
    }
}
