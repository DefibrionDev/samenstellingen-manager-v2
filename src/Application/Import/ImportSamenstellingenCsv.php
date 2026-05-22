<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

final readonly class ImportSamenstellingenCsv
{
    public function __construct(
        public string $csvPath,
        public string $familyHeadItemcode,
    ) {
    }
}
