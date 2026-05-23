<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

/**
 * Draait `SyncGroupAgainstAfasHandler` voor álle groepen in één call.
 *
 * Defensief: bij een lege AFAS-snapshot rapporteren we dat in de summary
 * in plaats van te throwen. Zo kan een fresh import of pull doorgaan zonder
 * de end-user een onverklaarbare fout te geven.
 */
final readonly class SyncAllGroupsHandler
{
    public function __construct(
        private GroupRepository $groups,
        private SyncGroupAgainstAfasHandler $sync,
        private AfasSamenstellingenRepository $afasRepository,
    ) {
    }

    public function __invoke(SyncAllGroups $command): SyncAllSummary
    {
        $summary = new SyncAllSummary();

        if ($this->afasRepository->countSnapshot() === 0) {
            $allGroups = $this->groups->findAll();
            $summary->groupsSkipped = count($allGroups);
            if ($summary->groupsSkipped > 0) {
                $summary->skipReasons[] = 'AFAS-snapshot is leeg — eerst `afas:pull` draaien voor matching.';
            }

            return $summary;
        }

        foreach ($this->groups->findAll() as $group) {
            $groupSummary = ($this->sync)(new SyncGroupAgainstAfas($group->familyHeadItemcode));
            ++$summary->groupsProcessed;
            $summary->matched += $groupSummary->matchCount();
            $summary->noMatch += $groupSummary->noMatchCount();
        }

        return $summary;
    }
}
