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
                'INSERT INTO groups (name, family_head_itemcode, model_name) VALUES (:name, :itemcode, :model)'
            );
            $stmt->execute([
                ':name' => $group->name,
                ':itemcode' => $group->familyHeadItemcode,
                ':model' => $group->modelName,
            ]);
        } catch (PDOException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'UNIQUE constraint failed: groups.name')) {
                throw GroupAlreadyExistsException::forName($group->name);
            }
            if (str_contains($message, 'UNIQUE constraint failed: groups.family_head_itemcode')) {
                throw GroupAlreadyExistsException::forFamilyHeadItemcode($group->familyHeadItemcode);
            }
            throw $e;
        }
    }

    public function findByName(string $name): ?Group
    {
        $stmt = $this->pdo->prepare(
            'SELECT name, family_head_itemcode, model_name FROM groups WHERE name = :name'
        );
        $stmt->execute([':name' => $name]);

        return $this->rowToGroup($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function findByFamilyHeadItemcode(string $familyHeadItemcode): ?Group
    {
        $stmt = $this->pdo->prepare(
            'SELECT name, family_head_itemcode, model_name FROM groups WHERE family_head_itemcode = :itemcode'
        );
        $stmt->execute([':itemcode' => $familyHeadItemcode]);

        return $this->rowToGroup($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function delete(string $familyHeadItemcode): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM groups WHERE family_head_itemcode = :itemcode'
        );
        $stmt->execute([':itemcode' => $familyHeadItemcode]);
        // Idempotent: rowCount kan 0 zijn als de groep niet bestond.
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT name, family_head_itemcode, model_name FROM groups ORDER BY name');
        if ($stmt === false) {
            return [];
        }

        $groups = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $group = $this->rowToGroup($row);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param mixed $row
     */
    private function rowToGroup($row): ?Group
    {
        if (!is_array($row)) {
            return null;
        }
        $name = $row['name'] ?? null;
        $itemcode = $row['family_head_itemcode'] ?? null;
        if (!is_string($name) || !is_string($itemcode)) {
            return null;
        }
        $model = $row['model_name'] ?? null;
        $modelStr = is_string($model) ? $model : null;

        return new Group($name, $itemcode, $modelStr);
    }
}
