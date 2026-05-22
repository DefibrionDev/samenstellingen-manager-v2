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

    public function saveForGroup(string $groupName, GroupBase $base): void
    {
        $groupId = $this->resolveGroupId($groupName);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO group_bases (group_id, itemcode, language_code, name)
                 VALUES (:group_id, :itemcode, :language_code, :name)'
            );
            $stmt->execute([
                ':group_id' => $groupId,
                ':itemcode' => $base->itemcode,
                ':language_code' => $base->languageCode,
                ':name' => $base->name,
            ]);
        } catch (PDOException $e) {
            if (
                str_contains($e->getMessage(), 'UNIQUE constraint failed')
                && str_contains($e->getMessage(), 'group_bases')
            ) {
                throw BaseAlreadyExistsException::forItemcodeInGroup($base->itemcode, $groupName);
            }
            throw $e;
        }
    }

    public function findAllForGroup(string $groupName): array
    {
        $groupId = $this->resolveGroupId($groupName);

        $stmt = $this->pdo->prepare(
            'SELECT itemcode, language_code, name
             FROM group_bases
             WHERE group_id = :group_id
             ORDER BY itemcode'
        );
        $stmt->execute([':group_id' => $groupId]);

        $bases = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (
                is_array($row)
                && is_string($row['itemcode'] ?? null)
                && is_string($row['language_code'] ?? null)
                && is_string($row['name'] ?? null)
            ) {
                $bases[] = new GroupBase($row['itemcode'], $row['language_code'], $row['name']);
            }
        }

        return $bases;
    }

    private function resolveGroupId(string $groupName): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM groups WHERE name = :name');
        $stmt->execute([':name' => $groupName]);
        $id = $stmt->fetchColumn();
        if (!is_int($id)) {
            throw GroupNotFoundException::forName($groupName);
        }

        return $id;
    }
}
