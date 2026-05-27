<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasPrijslijstenFetcher
{
    /**
     * @return list<AfasPrijslijst>
     */
    public function fetchAll(): array;
}
