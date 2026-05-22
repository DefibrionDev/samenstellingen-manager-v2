<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Import;

final class PortalImportSummary
{
    public int $rowsProcessed = 0;
    public int $rowsSkippedNoGroep = 0;
    public int $groupsCreated = 0;
    public int $basesCreated = 0;
    public int $baseItemsCreated = 0;

    /** @var list<array{groep: string, code: string, reason: string}> */
    public array $unresolved = [];
}
