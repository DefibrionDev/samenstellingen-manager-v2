<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenFetcher;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;

final readonly class PullAfasSamenstellingenHandler
{
    public function __construct(
        private AfasSamenstellingenFetcher $fetcher,
        private AfasSamenstellingenRepository $repository,
    ) {
    }

    /**
     * @return int Aantal samenstellingen in de nieuwe snapshot.
     */
    public function __invoke(PullAfasSamenstellingen $command): int
    {
        $samenstellingen = $this->fetcher->fetchAll();
        $this->repository->replaceSnapshot($samenstellingen);

        return count($samenstellingen);
    }
}
