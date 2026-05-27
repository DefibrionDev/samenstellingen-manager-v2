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

        // Verse snapshot → variant-match-status meteen opnieuw berekenen.
        $syncSummary = ($this->syncAllGroups)(new SyncAllGroups());

        return new PullAfasSamenstellingenResult(
            count($samenstellingen),
            count($activeArticles),
            $syncSummary,
            count($prijzen),
            count($prijslijsten),
        );
    }
}
