<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;

final readonly class ListBomBlacklistHandler
{
    public function __construct(private BomBlacklistRepository $repository)
    {
    }

    /**
     * @return list<BomBlacklistEntry>
     */
    public function __invoke(ListBomBlacklist $query): array
    {
        return $this->repository->findAll();
    }
}
