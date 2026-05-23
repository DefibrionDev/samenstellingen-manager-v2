<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasArticle;
use Defibrion\Samenstellingen\Domain\Afas\AfasArticleRepository;
use PDO;

final readonly class SqliteAfasArticleRepository implements AfasArticleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function replaceSnapshot(array $articles): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM afas_articles');
            // INSERT OR REPLACE: AFAS levert af en toe duplicate itemcodes in Get_Artikelen
            // (verschillende rijen voor hetzelfde artikel, bv. door taal-velden). De laatste rij
            // wint — we kunnen niet objectief de "juiste" naam kiezen, en deze tabel is alleen
            // bedoeld om de UI een leesbaar label te geven.
            $stmt = $this->pdo->prepare(
                'INSERT OR REPLACE INTO afas_articles (itemcode, name) VALUES (:itemcode, :name)'
            );
            foreach ($articles as $article) {
                $stmt->execute([
                    ':itemcode' => $article->itemcode,
                    ':name' => $article->name,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findByItemcode(string $itemcode): ?AfasArticle
    {
        $stmt = $this->pdo->prepare(
            'SELECT itemcode, name FROM afas_articles WHERE itemcode = :itemcode'
        );
        $stmt->execute([':itemcode' => $itemcode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $itemcode = $row['itemcode'] ?? null;
        $name = $row['name'] ?? null;
        if (!is_string($itemcode) || !is_string($name)) {
            return null;
        }

        return new AfasArticle($itemcode, $name);
    }

    public function countSnapshot(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM afas_articles');
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }
}
