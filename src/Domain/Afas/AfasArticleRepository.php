<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasArticleRepository
{
    /**
     * Vervang de snapshot in één transactie. Bestaande inhoud wordt gewist.
     *
     * @param list<AfasArticle> $articles
     */
    public function replaceSnapshot(array $articles): void;

    public function findByItemcode(string $itemcode): ?AfasArticle;

    public function countSnapshot(): int;
}
