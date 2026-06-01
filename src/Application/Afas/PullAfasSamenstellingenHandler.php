<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

use Defibrion\Samenstellingen\Application\Group\SyncAllGroups;
use Defibrion\Samenstellingen\Application\Group\SyncAllGroupsHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticlesFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstenFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijzenFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Group\FamilyHeadShiftDetector;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;

final readonly class PullAfasSamenstellingenHandler
{
    public function __construct(
        private AfasSamenstellingenFetcher $fetcher,
        private AfasSamenstellingenRepository $repository,
        private AfasArticlesFetcher $articlesFetcher,
        private AfasArticleRepository $articleRepository,
        private SyncAllGroupsHandler $syncAllGroups,
        private AfasPrijzenFetcher $prijzenFetcher,
        private AfasPrijsRepository $prijsRepository,
        private AfasPrijslijstenFetcher $prijslijstenFetcher,
        private AfasPrijslijstRepository $prijslijstRepository,
        private GroupRepository $groupRepository,
        private GroupBaseRepository $groupBaseRepository,
        private FamilyHeadShiftDetector $shiftDetector,
    ) {
    }

    public function __invoke(PullAfasSamenstellingen $command): PullAfasSamenstellingenResult
    {
        // Articles eerst — leveren de set geblokkeerde itemcodes die we daarna
        // uit alle andere snapshots (samenstellingen) filteren. Geblokkeerde
        // artikelen "bestaan niet" voor onze lokale tool.
        $allArticles = $this->articlesFetcher->fetchAll();
        $blockedItemcodes = [];
        $activeArticles = [];
        foreach ($allArticles as $article) {
            if ($article->geblokkeerd) {
                $blockedItemcodes[$article->itemcode] = true;
                continue;
            }
            $activeArticles[] = $article;
        }
        $this->articleRepository->replaceSnapshot($activeArticles);

        $allSamenstellingen = $this->fetcher->fetchAll();
        $samenstellingen = array_values(array_filter(
            $allSamenstellingen,
            static fn ($s): bool => !isset($blockedItemcodes[$s->itemcode]),
        ));
        $this->repository->replaceSnapshot($samenstellingen);

        $prijzen = $this->prijzenFetcher->fetchActive();
        $this->prijsRepository->replaceSnapshot($prijzen);

        $prijslijsten = $this->prijslijstenFetcher->fetchAll();
        $this->prijslijstRepository->replaceSnapshot($prijslijsten);

        // Auto-sync family-head wanneer AFAS' Itemcode_Parent verschoven is
        // voor de meerderheid van de bases in een groep. Zie PLAN.md §23.
        $shifts = $this->detectAndApplyShifts($samenstellingen);

        // Verse snapshot (en eventueel verschoven groepen) → variant-match-status
        // meteen opnieuw berekenen.
        $syncSummary = ($this->syncAllGroups)(new SyncAllGroups());

        return new PullAfasSamenstellingenResult(
            count($samenstellingen),
            count($activeArticles),
            $syncSummary,
            count($prijzen),
            count($prijslijsten),
            $shifts,
        );
    }

    /**
     * @param list<\Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling> $samenstellingen
     */
    private function detectAndApplyShifts(array $samenstellingen): int
    {
        $groups = $this->groupRepository->findAll();
        $basesByFamilyHead = [];
        foreach ($groups as $group) {
            $basesByFamilyHead[$group->familyHeadItemcode] = $this->groupBaseRepository->findAllForGroup($group->familyHeadItemcode);
        }

        $shifts = $this->shiftDetector->detect($groups, $basesByFamilyHead, $samenstellingen);
        if ($shifts === []) {
            return 0;
        }

        $applied = 0;
        foreach ($shifts as $shift) {
            try {
                $this->groupRepository->updateFamilyHeadItemcode($shift->oldHead, $shift->newHead);
                fwrite(STDERR, sprintf(
                    "[%s] [groups] family-head %s → %s (%d bases verschoven in AFAS)\n",
                    date('H:i:s'),
                    $shift->oldHead,
                    $shift->newHead,
                    $shift->baseCount,
                ));
                ++$applied;
            } catch (\Throwable $e) {
                fwrite(STDERR, sprintf(
                    "[%s] [groups] family-head %s → %s overgeslagen: %s\n",
                    date('H:i:s'),
                    $shift->oldHead,
                    $shift->newHead,
                    $e->getMessage(),
                ));
            }
        }

        return $applied;
    }
}
