<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticlesFetcher;

final readonly class HttpAfasArticlesFetcher implements AfasArticlesFetcher
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function fetchAll(): array
    {
        $rows = $this->client->getConnectorAll('Get_Artikelen');
        $articles = [];
        foreach ($rows as $row) {
            $itemcode = $row['Itemcode'] ?? null;
            $name = $row['Naam'] ?? '';
            if (!is_string($itemcode)) {
                continue;
            }
            $trimmed = trim($itemcode);
            if ($trimmed === '') {
                continue;
            }
            $articles[] = new AfasArticle($trimmed, is_string($name) ? $name : '');
        }

        return $articles;
    }
}
