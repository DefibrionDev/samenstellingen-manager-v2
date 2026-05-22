<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Group;

final readonly class SyncGroupAgainstAfas
{
    public function __construct(public string $familyHeadItemcode)
    {
    }
}
