<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Audit;

final readonly class MissingVariantRow
{
    /**
     * @param list<string> $verwachteBom
     */
    public function __construct(
        public string $groep,
        public string $baseNaam,
        public string $baseAfasSku,
        public string $accessoireItemcode,
        public string $accessoireLabel,
        public array $verwachteBom,
        public string $verwachteSkuVoorstel,
    ) {
    }
}
