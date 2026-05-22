<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

final readonly class ImportPortalCsv
{
    public function __construct(public string $csvPath)
    {
    }
}
