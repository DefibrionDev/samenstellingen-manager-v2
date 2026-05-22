<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupRepository;
use PDO;
use PDOException;

final readonly class SqliteGroupRepository implements GroupRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Group $group): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO groups (name, family_head_itemcode) VALUES (:name, :itemcode)'
            );
            $stmt->execute([
                ':name' => $group->name,
                ':itemcode' => $group->familyHeadItemcode,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed: groups.name')) {
                throw GroupAlreadyExistsException::forName($group->name);
            }
            throw $e;
        }
    }

    public function findByName(string $name): ?Group
    {
        $stmt = $this->pdo->prepare(
            'SELECT name, family_head_itemcode FROM groups WHERE name = :name'
        );
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $name = $row['name'] ?? null;
        $itemcode = $row['family_head_itemcode'] ?? null;
        if (!is_string($name) || !is_string($itemcode)) {
            return null;
        }

        return new Group($name, $itemcode);
    }
}
