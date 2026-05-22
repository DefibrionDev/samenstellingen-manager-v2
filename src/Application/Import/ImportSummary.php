<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

final class ImportSummary
{
    public int $basesCreated = 0;
    public int $basesSkipped = 0;
    public int $accessoiresCreated = 0;
    public int $accessoiresSkipped = 0;
    public int $accessoireLinksCreated = 0;
    public int $accessoireLinksSkipped = 0;
    public int $rowsProcessed = 0;
}
