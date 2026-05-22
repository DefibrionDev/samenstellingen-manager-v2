<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Import;

interface CsvSamenstellingenReader
{
    /**
     * @return iterable<CsvSamenstellingenRow>
     */
    public function read(string $path): iterable;
}
