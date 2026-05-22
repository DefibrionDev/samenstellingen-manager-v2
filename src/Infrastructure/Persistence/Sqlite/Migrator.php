<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use PDO;
use RuntimeException;

final readonly class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsDir,
    ) {
    }

    public function migrate(): void
    {
        $this->ensureMigrationsTable();
        $applied = $this->loadApplied();

        $files = glob($this->migrationsDir . '/*.sql');
        if ($files === false) {
            throw new RuntimeException(sprintf('Kan migratiemap niet scannen: %s', $this->migrationsDir));
        }
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException(sprintf('Kan migratie niet lezen: %s', $file));
            }

            $this->pdo->exec($sql);
            $this->recordApplied($name);
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS _migrations (
                name TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    /**
     * @return array<string, true>
     */
    private function loadApplied(): array
    {
        $stmt = $this->pdo->query('SELECT name FROM _migrations');
        if ($stmt === false) {
            return [];
        }

        $applied = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            if (is_string($name)) {
                $applied[$name] = true;
            }
        }

        return $applied;
    }

    private function recordApplied(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO _migrations (name) VALUES (:name)');
        $stmt->execute([':name' => $name]);
    }
}
