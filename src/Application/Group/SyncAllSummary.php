<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final class SyncAllSummary
{
    public int $groupsProcessed = 0;
    public int $groupsSkipped = 0;
    public int $matched = 0;
    public int $noMatch = 0;

    /**
     * @var list<string> Reden(en) waarom er groepen overgeslagen zijn (bv. lege snapshot).
     */
    public array $skipReasons = [];
}
