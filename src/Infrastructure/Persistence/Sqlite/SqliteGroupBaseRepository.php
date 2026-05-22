<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Group\BaseAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use PDO;
use PDOException;

final readonly class SqliteGroupBaseRepository implements GroupBaseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function saveForGroup(string $familyHeadItemcode, GroupBase $base): GroupBase
    {
        $groupId = $this->resolveGroupId($familyHeadItemcode);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO group_bases (group_id, name) VALUES (:group_id, :name)'
            );
            $stmt->execute([
                ':group_id' => $groupId,
                ':name' => $base->name,
            ]);
        } catch (PDOException $e) {
            if (
                str_contains($e->getMessage(), 'UNIQUE constraint failed')
                && str_contains($e->getMessage(), 'group_bases')
            ) {
                throw BaseAlreadyExistsException::forNameInGroup($base->name, $familyHeadItemcode);
            }
            throw $e;
        }

        return $base->withId((int) $this->pdo->lastInsertId());
    }

    public function findById(int $baseId): ?GroupBase
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM group_bases WHERE id = :id');
        $stmt->execute([':id' => $baseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;
        if (!is_int($id) || !is_string($name)) {
            return null;
        }

        return new GroupBase($id, $name);
    }

    public function findAllForGroup(string $familyHeadItemcode): array
    {
        $groupId = $this->resolveGroupId($familyHeadItemcode);

        $stmt = $this->pdo->prepare(
            'SELECT id, name FROM group_bases WHERE group_id = :group_id ORDER BY id'
        );
        $stmt->execute([':group_id' => $groupId]);

        $bases = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['id'] ?? null;
            $name = $row['name'] ?? null;
            if (is_int($id) && is_string($name)) {
                $bases[] = new GroupBase($id, $name);
            }
        }

        return $bases;
    }

    private function resolveGroupId(string $familyHeadItemcode): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM groups WHERE family_head_itemcode = :itemcode');
        $stmt->execute([':itemcode' => $familyHeadItemcode]);
        $id = $stmt->fetchColumn();
        if (!is_int($id)) {
            throw GroupNotFoundException::forFamilyHeadItemcode($familyHeadItemcode);
        }

        return $id;
    }
}
