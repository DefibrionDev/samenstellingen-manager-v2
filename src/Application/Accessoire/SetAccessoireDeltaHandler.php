<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Accessoire;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use InvalidArgumentException;

final readonly class SetAccessoireDeltaHandler
{
    public function __construct(private AccessoireRepository $repository)
    {
    }

    public function __invoke(SetAccessoireDelta $command): void
    {
        if ($command->deltaCents < 0) {
            throw new InvalidArgumentException('Delta mag niet negatief zijn.');
        }
        $this->repository->updateDelta($command->itemcode, $command->deltaCents);
    }
}
