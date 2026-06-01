<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Website\Website;
use Defibrion\Samenstellingen\Domain\Website\WebsiteAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use PDO;
use PDOException;

final readonly class SqliteWebsiteRepository implements WebsiteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Website $website): Website
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO websites (name, ff_sync_uuid, ff_tonen_uuid)
                 VALUES (:name, :sync, :tonen)'
            );
            $stmt->execute([
                ':name' => $website->name,
                ':sync' => $website->ffSyncUuid,
                ':tonen' => $website->ffTonenUuid,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed') && str_contains($e->getMessage(), 'websites.name')) {
                throw WebsiteAlreadyExistsException::forName($website->name);
            }
            throw $e;
        }

        return $website->withId((int) $this->pdo->lastInsertId());
    }

    public function findByName(string $name): ?Website
    {
        $stmt = $this->pdo->prepare('SELECT id, name, ff_sync_uuid, ff_tonen_uuid FROM websites WHERE name = :name');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->rowToWebsite($row) : null;
    }

    public function findById(int $id): ?Website
    {
        $stmt = $this->pdo->prepare('SELECT id, name, ff_sync_uuid, ff_tonen_uuid FROM websites WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->rowToWebsite($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, ff_sync_uuid, ff_tonen_uuid FROM websites ORDER BY name');
        if ($stmt === false) {
            return [];
        }
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $website = $this->rowToWebsite($row);
                if ($website !== null) {
                    $result[] = $website;
                }
            }
        }

        return $result;
    }

    public function delete(string $name): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM websites WHERE name = :name');
        $stmt->execute([':name' => $name]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToWebsite(array $row): ?Website
    {
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;
        $sync = $row['ff_sync_uuid'] ?? null;
        $tonen = $row['ff_tonen_uuid'] ?? null;
        if (!is_int($id) || !is_string($name) || !is_string($sync) || !is_string($tonen)) {
            return null;
        }

        return new Website($id, $name, $sync, $tonen);
    }
}
