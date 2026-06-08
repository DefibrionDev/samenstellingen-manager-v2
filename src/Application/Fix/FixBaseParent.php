<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixBaseParent
{
    public function __construct(public bool $apply)
    {
    }
}
