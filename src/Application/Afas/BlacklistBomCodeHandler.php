<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;

final readonly class BlacklistBomCodeHandler
{
    public function __construct(private BomBlacklistRepository $repository)
    {
    }

    public function __invoke(BlacklistBomCode $command): BomBlacklistEntry
    {
        $entry = new BomBlacklistEntry($command->itemcode, $command->reason);
        $this->repository->save($entry);

        return $entry;
    }
}
