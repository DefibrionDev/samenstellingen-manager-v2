<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

use Defibrion\Samenstellingen\Application\Group\SyncAllSummary;

final class PortalImportSummary
{
    public int $rowsProcessed = 0;
    public int $rowsSkippedNoGroep = 0;
    public int $groupsCreated = 0;
    public int $basesCreated = 0;
    public int $baseItemsCreated = 0;

    /** @var list<array{groep: string, code: string, articleName: string, reason: string}> */
    public array $unresolved = [];

    public ?SyncAllSummary $sync = null;
}
