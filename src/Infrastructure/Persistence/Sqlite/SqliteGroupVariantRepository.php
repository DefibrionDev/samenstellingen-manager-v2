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

    public function regenerateForGroup(string $groupName): void
    {
        $groupId = $this->resolveGroupId($groupName);
        $baseItemcodes = $this->loadBaseItemcodes($groupId);
        $accessoireIds = $this->loadLinkedAccessoireIds($groupId);

        $insert = $this->pdo->prepare(
            'INSERT OR IGNORE INTO group_variants (group_id, base_itemcode, accessoire_id)
             VALUES (:group_id, :base_itemcode, :accessoire_id)'
        );

        $expectedPairs = [];
        foreach ($baseItemcodes as $itemcode) {
            $insert->execute([
                ':group_id' => $groupId,
                ':base_itemcode' => $itemcode,
                ':accessoire_id' => null,
            ]);
            $expectedPairs[] = [$itemcode, null];

            foreach ($accessoireIds as $accessoireId) {
                $insert->execute([
                    ':group_id' => $groupId,
                    ':base_itemcode' => $itemcode,
                    ':accessoire_id' => $accessoireId,
                ]);
                $expectedPairs[] = [$itemcode, $accessoireId];
            }
        }

        $this->deleteOrphans($groupId, $expectedPairs);
    }

    /**
     * @return list<string>
     */
    private function loadBaseItemcodes(int $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT itemcode FROM group_bases WHERE group_id = :group_id');
        $stmt->execute([':group_id' => $groupId]);

        $itemcodes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            if (is_string($value)) {
                $itemcodes[] = $value;
            }
        }

        return $itemcodes;
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

    public function findAllForGroup(string $groupName): array
    {
        $groupId = $this->resolveGroupId($groupName);

        $stmt = $this->pdo->prepare(
            'SELECT
                gv.base_itemcode,
                gb.language_code AS base_language_code,
                gb.name AS base_name,
                a.itemcode AS accessoire_itemcode,
                a.label AS accessoire_label,
                gv.afas_samenstelling_itemcode
             FROM group_variants gv
             INNER JOIN group_bases gb
                 ON gb.group_id = gv.group_id AND gb.itemcode = gv.base_itemcode
             LEFT JOIN accessoires a
                 ON a.id = gv.accessoire_id
             WHERE gv.group_id = :group_id
             ORDER BY gv.base_itemcode,
                      CASE WHEN gv.accessoire_id IS NULL THEN 0 ELSE 1 END,
                      a.itemcode'
        );
        $stmt->execute([':group_id' => $groupId]);

        $variants = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $baseItemcode = $row['base_itemcode'] ?? null;
            $baseLanguageCode = $row['base_language_code'] ?? null;
            $baseName = $row['base_name'] ?? null;
            if (!is_string($baseItemcode) || !is_string($baseLanguageCode) || !is_string($baseName)) {
                continue;
            }

            $accessoireItemcode = $row['accessoire_itemcode'] ?? null;
            $accessoireLabel = $row['accessoire_label'] ?? null;
            $afasItemcode = $row['afas_samenstelling_itemcode'] ?? null;

            $variants[] = new GroupVariant(
                $baseItemcode,
                $baseLanguageCode,
                $baseName,
                is_string($accessoireItemcode) ? $accessoireItemcode : null,
                is_string($accessoireLabel) ? $accessoireLabel : null,
                is_string($afasItemcode) ? $afasItemcode : null,
            );
        }

        return $variants;
    }

    /**
     * @param list<array{0: string, 1: int|null}> $expectedPairs
     */
    private function deleteOrphans(int $groupId, array $expectedPairs): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT base_itemcode, accessoire_id FROM group_variants WHERE group_id = :group_id'
        );
        $stmt->execute([':group_id' => $groupId]);

        $expectedKeys = [];
        foreach ($expectedPairs as [$itemcode, $accessoireId]) {
            $expectedKeys[$itemcode . '|' . ($accessoireId ?? '')] = true;
        }

        $delete = $this->pdo->prepare(
            'DELETE FROM group_variants
             WHERE group_id = :group_id
               AND base_itemcode = :base_itemcode
               AND ((:accessoire_id IS NULL AND accessoire_id IS NULL)
                    OR accessoire_id = :accessoire_id)'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itemcode = $row['base_itemcode'] ?? null;
            $accessoireId = $row['accessoire_id'] ?? null;
            if (!is_string($itemcode)) {
                continue;
            }
            $key = $itemcode . '|' . (is_int($accessoireId) ? (string) $accessoireId : '');
            if (isset($expectedKeys[$key])) {
                continue;
            }

            $delete->execute([
                ':group_id' => $groupId,
                ':base_itemcode' => $itemcode,
                ':accessoire_id' => is_int($accessoireId) ? $accessoireId : null,
            ]);
        }
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
