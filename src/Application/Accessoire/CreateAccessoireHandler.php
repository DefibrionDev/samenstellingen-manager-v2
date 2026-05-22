<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Accessoire;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;

final readonly class CreateAccessoireHandler
{
    public function __construct(private AccessoireRepository $repository)
    {
    }

    public function __invoke(CreateAccessoire $command): Accessoire
    {
        $accessoire = new Accessoire($command->itemcode, $command->label);
        $this->repository->save($accessoire);

        return $accessoire;
    }
}
