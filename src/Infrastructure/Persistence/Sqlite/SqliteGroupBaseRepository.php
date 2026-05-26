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
                'INSERT INTO group_bases (group_id, name, language_code, afas_itemcode)
                 VALUES (:group_id, :name, :language_code, :afas_itemcode)'
            );
            $stmt->execute([
                ':group_id' => $groupId,
                ':name' => $base->name,
                ':language_code' => $base->languageCode,
                ':afas_itemcode' => $base->afasItemcode,
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
        $stmt = $this->pdo->prepare(
            'SELECT id, name, language_code, afas_itemcode FROM group_bases WHERE id = :id'
        );
        $stmt->execute([':id' => $baseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->rowToBase($row) : null;
    }

    public function findAllForGroup(string $familyHeadItemcode): array
    {
        $groupId = $this->resolveGroupId($familyHeadItemcode);

        $stmt = $this->pdo->prepare(
            'SELECT id, name, language_code, afas_itemcode FROM group_bases WHERE group_id = :group_id ORDER BY id'
        );
        $stmt->execute([':group_id' => $groupId]);

        $bases = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $base = $this->rowToBase($row);
            if ($base !== null) {
                $bases[] = $base;
            }
        }

        return $bases;
    }

    public function delete(int $baseId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM group_bases WHERE id = :id');
        $stmt->execute([':id' => $baseId]);
    }

    public function findFamilyHeadForBase(int $baseId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.family_head_itemcode
               FROM groups g
               JOIN group_bases b ON b.group_id = g.id
              WHERE b.id = :id'
        );
        $stmt->execute([':id' => $baseId]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public function findAllAfasItemcodes(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT afas_itemcode FROM group_bases WHERE afas_itemcode IS NOT NULL'
        );
        if ($stmt === false) {
            return [];
        }

        $codes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            if (is_string($value) && $value !== '') {
                $codes[] = $value;
            }
        }

        return $codes;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToBase(array $row): ?GroupBase
    {
        $id = $row['id'] ?? null;
        $name = $row['name'] ?? null;
        $language = $row['language_code'] ?? null;
        if (!is_int($id) || !is_string($name) || !is_string($language) || trim($language) === '') {
            // Legacy rij zonder taal — overslaan (taal is verplicht sinds slice 11-fix).
            return null;
        }

        $afasRaw = $row['afas_itemcode'] ?? null;
        $afas = is_string($afasRaw) ? $afasRaw : null;

        return new GroupBase($id, $name, $language, $afas);
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
