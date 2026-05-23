<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\InMemory;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;

final class InMemoryAfasArticleRepository implements AfasArticleRepository
{
    /** @var array<string, AfasArticle> */
    private array $byItemcode = [];

    public function replaceSnapshot(array $articles): void
    {
        $this->byItemcode = [];
        foreach ($articles as $article) {
            $this->byItemcode[$article->itemcode] = $article;
        }
    }

    public function findByItemcode(string $itemcode): ?AfasArticle
    {
        return $this->byItemcode[$itemcode] ?? null;
    }

    public function countSnapshot(): int
    {
        return count($this->byItemcode);
    }
}
