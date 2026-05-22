<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\GroupVariant;
use Defibrion\Samenstellingen\Domain\Group\GroupVariantRepository;
use PDO;

final readonly class SqliteGroupVariantRepository implements GroupVariantRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function regenerateForGroup(string $familyHeadItemcode): void
    {
        $groupId = $this->resolveGroupId($familyHeadItemcode);
        $baseIds = $this->loadBaseIds($groupId);
        $accessoireIds = $this->loadLinkedAccessoireIds($groupId);

        $insert = $this->pdo->prepare(
            'INSERT OR IGNORE INTO group_variants (base_id, accessoire_id)
             VALUES (:base_id, :accessoire_id)'
        );

        $expectedPairs = [];
        foreach ($baseIds as $baseId) {
            $insert->execute([
                ':base_id' => $baseId,
                ':accessoire_id' => null,
            ]);
            $expectedPairs[] = [$baseId, null];

            foreach ($accessoireIds as $accessoireId) {
                $insert->execute([
                    ':base_id' => $baseId,
                    ':accessoire_id' => $accessoireId,
                ]);
                $expectedPairs[] = [$baseId, $accessoireId];
            }
        }

        $this->deleteOrphans($baseIds, $expectedPairs);
    }

    public function findAllForGroup(string $familyHeadItemcode): array
    {
        $groupId = $this->resolveGroupId($familyHeadItemcode);

        $stmt = $this->pdo->prepare(
            'SELECT
                gv.id,
                gv.base_id,
                gb.name AS base_name,
                a.itemcode AS accessoire_itemcode,
                a.label AS accessoire_label,
                gv.afas_samenstelling_itemcode
             FROM group_variants gv
             INNER JOIN group_bases gb ON gb.id = gv.base_id
             LEFT JOIN accessoires a ON a.id = gv.accessoire_id
             WHERE gb.group_id = :group_id
             ORDER BY gv.base_id,
                      CASE WHEN gv.accessoire_id IS NULL THEN 0 ELSE 1 END,
                      a.itemcode'
        );
        $stmt->execute([':group_id' => $groupId]);

        $variants = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $baseId = $row['base_id'] ?? null;
            $baseName = $row['base_name'] ?? null;
            if (!is_int($id) || !is_int($baseId) || !is_string($baseName)) {
                continue;
            }

            $accessoireItemcode = $row['accessoire_itemcode'] ?? null;
            $accessoireLabel = $row['accessoire_label'] ?? null;
            $afasItemcode = $row['afas_samenstelling_itemcode'] ?? null;

            $variants[] = new GroupVariant(
                $id,
                $baseId,
                $baseName,
                is_string($accessoireItemcode) ? $accessoireItemcode : null,
                is_string($accessoireLabel) ? $accessoireLabel : null,
                is_string($afasItemcode) ? $afasItemcode : null,
            );
        }

        return $variants;
    }

    /**
     * @return list<int>
     */
    private function loadBaseIds(int $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM group_bases WHERE group_id = :group_id ORDER BY id');
        $stmt->execute([':group_id' => $groupId]);

        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            if (is_int($value)) {
                $ids[] = $value;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function loadLinkedAccessoireIds(int $groupId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT accessoire_id FROM group_accessoires WHERE group_id = :group_id'
        );
        $stmt->execute([':group_id' => $groupId]);

        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            if (is_int($value)) {
                $ids[] = $value;
            }
        }

        return $ids;
    }

    /**
     * @param list<int>                       $baseIds
     * @param list<array{0: int, 1: int|null}> $expectedPairs
     */
    private function deleteOrphans(array $baseIds, array $expectedPairs): void
    {
        if ($baseIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($baseIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT base_id, accessoire_id FROM group_variants WHERE base_id IN ({$placeholders})"
        );
        $stmt->execute($baseIds);

        $expectedKeys = [];
        foreach ($expectedPairs as [$baseId, $accessoireId]) {
            $expectedKeys[$baseId . '|' . ($accessoireId ?? '')] = true;
        }

        $delete = $this->pdo->prepare(
            'DELETE FROM group_variants
             WHERE base_id = :base_id
               AND ((:accessoire_id IS NULL AND accessoire_id IS NULL)
                    OR accessoire_id = :accessoire_id)'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $baseId = $row['base_id'] ?? null;
            $accessoireId = $row['accessoire_id'] ?? null;
            if (!is_int($baseId)) {
                continue;
            }
            $key = $baseId . '|' . (is_int($accessoireId) ? (string) $accessoireId : '');
            if (isset($expectedKeys[$key])) {
                continue;
            }
            $delete->execute([
                ':base_id' => $baseId,
                ':accessoire_id' => is_int($accessoireId) ? $accessoireId : null,
            ]);
        }
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
