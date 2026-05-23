<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasArticlesFetcher
{
    /**
     * Haal alle AFAS-artikelen op (itemcode + naam).
     *
     * @return list<AfasArticle>
     */
    public function fetchAll(): array;
}
