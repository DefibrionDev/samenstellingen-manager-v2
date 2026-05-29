<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

/**
 * Orchestreert de chained-flow:
 *   1. variants:fix-missing POST nieuwe FbComposition-records
 *   2. snapshot vernieuwen zodat de nieuwe varianten in `afas_articles` +
 *      `afas_samenstellingen` + `afas_prijzen` zichtbaar zijn
 *   3. prices:fix-missing scoped op die net-aangemaakte itemcodes
 *
 * Dry-run, geen-applied of `--skip-prices` slaat stappen 2-3 over.
 * Zie PLAN.md §20.
 */
final readonly class FixMissingVariantsWithPricesHandler
{
    public function __construct(
        private FixMissingVariantsHandler $variants,
        private VariantSnapshotRefresher $refresher,
        private FixPriceMissingHandler $prices,
    ) {
    }

    public function __invoke(FixMissingVariants $command): FixMissingVariantsWithPricesResult
    {
        $variantsResult = ($this->variants)($command);

        if (!$command->apply || $command->skipPrices || $variantsResult->appliedCount === 0) {
            return new FixMissingVariantsWithPricesResult($variantsResult, null);
        }

        $this->refresher->refresh();

        $appliedItemcodes = [];
        $appliedIndex = $variantsResult->appliedCount;
        foreach ($variantsResult->plans as $i => $plan) {
            if ($i >= $appliedIndex) {
                break;
            }
            // Een plan dat in failures voorkomt is niet applied — overslaan.
            if ($this->planFailed($plan, $variantsResult->failures)) {
                continue;
            }
            $appliedItemcodes[] = $plan->afasItemcode;
        }

        $pricesResult = ($this->prices)(new FixPriceMissing(
            apply: true,
            onlyForVariantItemcodes: $appliedItemcodes,
        ));

        return new FixMissingVariantsWithPricesResult($variantsResult, $pricesResult);
    }

    /**
     * @param list<array{plan: VariantFixMissingPlan, error: string}> $failures
     */
    private function planFailed(VariantFixMissingPlan $plan, array $failures): bool
    {
        foreach ($failures as $failure) {
            if ($failure['plan']->afasItemcode === $plan->afasItemcode) {
                return true;
            }
        }

        return false;
    }
}
