<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Import;

interface PortalCsvReader
{
    /**
     * @return iterable<PortalCsvRow>
     */
    public function read(string $path): iterable;
}
