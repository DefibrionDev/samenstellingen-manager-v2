<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Afas;

final readonly class PullAfasSamenstellingenResult
{
    public function __construct(
        public int $samenstellingen,
        public int $articles,
    ) {
    }
}
