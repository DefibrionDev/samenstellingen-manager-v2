<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Group\BaseItemAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\BaseNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItemRepository;
use PDO;
use PDOException;

final readonly class SqliteGroupBaseItemRepository implements GroupBaseItemRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function saveForBase(int $baseId, GroupBaseItem $item): void
    {
        $this->assertBaseExists($baseId);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO group_base_items (base_id, itemcode, name)
                 VALUES (:base_id, :itemcode, :name)'
            );
            $stmt->execute([
                ':base_id' => $baseId,
                ':itemcode' => $item->itemcode,
                ':name' => $item->name,
            ]);
        } catch (PDOException $e) {
            if (
                str_contains($e->getMessage(), 'UNIQUE constraint failed')
                && str_contains($e->getMessage(), 'group_base_items')
            ) {
                throw BaseItemAlreadyExistsException::forItemcodeInBase($item->itemcode, $baseId);
            }
            throw $e;
        }
    }

    public function findAllForBase(int $baseId): array
    {
        $this->assertBaseExists($baseId);

        $stmt = $this->pdo->prepare(
            'SELECT itemcode, name FROM group_base_items
             WHERE base_id = :base_id
             ORDER BY itemcode'
        );
        $stmt->execute([':base_id' => $baseId]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itemcode = $row['itemcode'] ?? null;
            $name = $row['name'] ?? null;
            if (is_string($itemcode) && is_string($name)) {
                $items[] = new GroupBaseItem($itemcode, $name);
            }
        }

        return $items;
    }

    private function assertBaseExists(int $baseId): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM group_bases WHERE id = :id');
        $stmt->execute([':id' => $baseId]);
        if ($stmt->fetchColumn() === false) {
            throw BaseNotFoundException::forId($baseId);
        }
    }
}
