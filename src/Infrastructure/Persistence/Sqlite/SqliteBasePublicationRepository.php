<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Website\BasePublication;
use Defibrion\Samenstellingen\Domain\Website\BasePublicationRepository;
use PDO;

final readonly class SqliteBasePublicationRepository implements BasePublicationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function setPublished(int $baseId, int $websiteId, bool $published): BasePublication
    {
        $existing = $this->find($baseId, $websiteId);
        if ($existing !== null) {
            $stmt = $this->pdo->prepare('UPDATE base_publications SET published = :published WHERE id = :id');
            $stmt->execute([':published' => $published ? 1 : 0, ':id' => $existing->id]);

            return new BasePublication($existing->id, $baseId, $websiteId, $published);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO base_publications (base_id, website_id, published)
             VALUES (:base, :website, :published)'
        );
        $stmt->execute([
            ':base' => $baseId,
            ':website' => $websiteId,
            ':published' => $published ? 1 : 0,
        ]);

        return new BasePublication((int) $this->pdo->lastInsertId(), $baseId, $websiteId, $published);
    }

    public function find(int $baseId, int $websiteId): ?BasePublication
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, base_id, website_id, published FROM base_publications WHERE base_id = :base AND website_id = :website'
        );
        $stmt->execute([':base' => $baseId, ':website' => $websiteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->rowToPublication($row) : null;
    }

    public function findAllForBase(int $baseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, base_id, website_id, published FROM base_publications WHERE base_id = :base ORDER BY id'
        );
        $stmt->execute([':base' => $baseId]);

        return $this->rowsToPublications($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findAllForWebsite(int $websiteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, base_id, website_id, published FROM base_publications WHERE website_id = :website ORDER BY id'
        );
        $stmt->execute([':website' => $websiteId]);

        return $this->rowsToPublications($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<int, mixed> $rows
     * @return list<BasePublication>
     */
    private function rowsToPublications(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $pub = $this->rowToPublication($row);
                if ($pub !== null) {
                    $result[] = $pub;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToPublication(array $row): ?BasePublication
    {
        $id = $row['id'] ?? null;
        $baseId = $row['base_id'] ?? null;
        $websiteId = $row['website_id'] ?? null;
        $published = $row['published'] ?? null;
        if (!is_int($id) || !is_int($baseId) || !is_int($websiteId)) {
            return null;
        }

        return new BasePublication($id, $baseId, $websiteId, (int) $published === 1);
    }
}
