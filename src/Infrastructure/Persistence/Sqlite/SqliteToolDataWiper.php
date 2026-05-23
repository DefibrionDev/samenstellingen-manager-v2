<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Tool\ToolDataWiper;
use PDO;

final readonly class SqliteToolDataWiper implements ToolDataWiper
{
    public function __construct(private PDO $pdo)
    {
    }

    public function wipe(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM group_variants');
            $this->pdo->exec('DELETE FROM group_base_items');
            $this->pdo->exec('DELETE FROM group_accessoires');
            $this->pdo->exec('DELETE FROM group_bases');
            $this->pdo->exec('DELETE FROM groups');
            $this->pdo->exec(
                "DELETE FROM sqlite_sequence
                 WHERE name IN ('groups', 'group_variants', 'group_bases')"
            );
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
