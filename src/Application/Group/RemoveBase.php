<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class RemoveBase
{
    public function __construct(public int $baseId)
    {
    }
}
