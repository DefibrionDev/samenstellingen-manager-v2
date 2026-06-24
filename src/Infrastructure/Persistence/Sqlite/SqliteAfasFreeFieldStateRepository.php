<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Application\Publications\AfasFreeFieldStateRepository;
use PDO;
use Throwable;

final readonly class SqliteAfasFreeFieldStateRepository implements AfasFreeFieldStateRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function replaceSnapshot(array $state): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM afas_free_field_state');
            $stmt = $this->pdo->prepare(
                'INSERT INTO afas_free_field_state (afas_itemcode, free_field_uuid, enabled) VALUES (?, ?, ?)',
            );
            foreach ($state as $itemcode => $flags) {
                foreach ($flags as $uuid => $enabled) {
                    $stmt->execute([(string) $itemcode, $uuid, $enabled ? 1 : 0]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function readAll(): array
    {
        $stmt = $this->pdo->query('SELECT afas_itemcode, free_field_uuid, enabled FROM afas_free_field_state');
        if ($stmt === false) {
            return [];
        }
        $state = [];
        /** @var array{afas_itemcode: string, free_field_uuid: string, enabled: int|string} $row */
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $state[(string) $row['afas_itemcode']][(string) $row['free_field_uuid']] = (int) $row['enabled'] === 1;
        }

        return $state;
    }
}
