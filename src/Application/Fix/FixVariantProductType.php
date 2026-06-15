<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

final readonly class FixVariantProductType
{
    public function __construct(public bool $apply)
    {
    }
}
