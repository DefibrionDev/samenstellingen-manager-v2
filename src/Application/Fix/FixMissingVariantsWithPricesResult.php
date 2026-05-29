<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixMissingVariantsWithPricesResult
{
    public function __construct(
        public FixMissingVariantsResult $variants,
        public ?FixPriceMissingResult $prices,
    ) {
    }
}
