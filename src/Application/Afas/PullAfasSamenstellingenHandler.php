<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

use Defibrion\Samenstellingen\Application\Group\SyncAllGroups;
use Defibrion\Samenstellingen\Application\Group\SyncAllGroupsHandler;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticlesFetcher;
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
    ) {
    }

    public function __invoke(PullAfasSamenstellingen $command): PullAfasSamenstellingenResult
    {
        $samenstellingen = $this->fetcher->fetchAll();
        $this->repository->replaceSnapshot($samenstellingen);

        $articles = $this->articlesFetcher->fetchAll();
        $this->articleRepository->replaceSnapshot($articles);

        // Verse snapshot → variant-match-status meteen opnieuw berekenen,
        // anders blijft de UI (en de missing-variants pagina) stale tot iemand
        // handmatig `group:sync-afas` per groep draait.
        $syncSummary = ($this->syncAllGroups)(new SyncAllGroups());

        return new PullAfasSamenstellingenResult(
            count($samenstellingen),
            count($articles),
            $syncSummary,
        );
    }
}
