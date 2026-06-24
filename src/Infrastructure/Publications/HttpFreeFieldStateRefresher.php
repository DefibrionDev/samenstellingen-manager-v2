<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Publications;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateReader;
use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateRepository;
use Defibrion\Samenstellingen\Application\Publications\FreeFieldStateRefresher;

/**
 * Leest de live AFAS free-field-state (via {@see AfasFreeFieldStateReader},
 * doorgaans de Get_Artikelen-reader) en schrijft die naar de lokale snapshot
 * ({@see AfasFreeFieldStateRepository}).
 */
final readonly class HttpFreeFieldStateRefresher implements FreeFieldStateRefresher
{
    public function __construct(
        private AfasFreeFieldStateReader $liveReader,
        private AfasFreeFieldStateRepository $repository,
    ) {
    }

    public function refresh(): void
    {
        $this->repository->replaceSnapshot($this->liveReader->readAll());
    }
}
