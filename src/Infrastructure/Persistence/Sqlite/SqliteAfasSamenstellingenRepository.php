<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstelling;
use Defibrion\Samenstellingen\Domain\Afas\AfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Domain\Afas\DuplicateDetector;
use PDO;

final readonly class SqliteAfasSamenstellingenRepository implements AfasSamenstellingenRepository
{
    private DuplicateDetector $detector;

    public function __construct(private PDO $pdo)
    {
        $this->detector = new DuplicateDetector();
    }

    public function replaceSnapshot(array $samenstellingen): void
    {
        $annotated = $this->detector->annotate($samenstellingen);

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM afas_samenstelling_bom');
            $this->pdo->exec('DELETE FROM afas_samenstellingen');
            $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'afas_samenstellingen'");

            $insertSamenstelling = $this->pdo->prepare(
                'INSERT INTO afas_samenstellingen (itemcode, name, itemcode_parent, duplicate_of_itemcode)
                 VALUES (:itemcode, :name, :itemcode_parent, :duplicate_of)'
            );
            $insertBomRow = $this->pdo->prepare(
                'INSERT INTO afas_samenstelling_bom (afas_samenstelling_id, component_itemcode)
                 VALUES (:id, :component)'
            );

            foreach ($annotated as $samenstelling) {
                $insertSamenstelling->execute([
                    ':itemcode' => $samenstelling->itemcode,
                    ':name' => $samenstelling->name,
                    ':itemcode_parent' => $samenstelling->itemcodeParent,
                    ':duplicate_of' => $samenstelling->duplicateOfItemcode,
                ]);
                $id = (int) $this->pdo->lastInsertId();
                foreach ($samenstelling->bomItemcodes as $component) {
                    $insertBomRow->execute([
                        ':id' => $id,
                        ':component' => $component,
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findAll(): array
    {
        return $this->loadWhere('');
    }

    public function findAllCanonical(): array
    {
        return $this->loadWhere('WHERE duplicate_of_itemcode IS NULL');
    }

    public function findAllDuplicates(): array
    {
        return $this->loadWhere('WHERE duplicate_of_itemcode IS NOT NULL');
    }

    /**
     * @return list<AfasSamenstelling>
     */
    private function loadWhere(string $whereClause): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, itemcode, name, itemcode_parent, duplicate_of_itemcode
             FROM afas_samenstellingen
             {$whereClause}
             ORDER BY itemcode"
        );
        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{itemcode: string, name: string, itemcode_parent: ?string, duplicate_of: ?string, bom: list<string>}> $byId */
        $byId = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['id'] ?? null;
            $itemcode = $row['itemcode'] ?? null;
            $name = $row['name'] ?? null;
            $parent = $row['itemcode_parent'] ?? null;
            $duplicateOf = $row['duplicate_of_itemcode'] ?? null;
            if (!is_int($id) || !is_string($itemcode) || !is_string($name)) {
                continue;
            }
            $byId[$id] = [
                'itemcode' => $itemcode,
                'name' => $name,
                'itemcode_parent' => is_string($parent) ? $parent : null,
                'duplicate_of' => is_string($duplicateOf) ? $duplicateOf : null,
                'bom' => [],
            ];
        }

        if ($byId === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($byId), '?'));
        $bomStmt = $this->pdo->prepare(
            "SELECT afas_samenstelling_id, component_itemcode
             FROM afas_samenstelling_bom
             WHERE afas_samenstelling_id IN ({$placeholders})"
        );
        $bomStmt->execute(array_keys($byId));
        foreach ($bomStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['afas_samenstelling_id'] ?? null;
            $component = $row['component_itemcode'] ?? null;
            if (is_int($id) && is_string($component) && isset($byId[$id])) {
                $byId[$id]['bom'][] = $component;
            }
        }

        $result = [];
        foreach ($byId as $entry) {
            $result[] = new AfasSamenstelling(
                $entry['itemcode'],
                $entry['name'],
                $entry['itemcode_parent'],
                $entry['bom'],
                $entry['duplicate_of'],
            );
        }

        return $result;
    }

    public function countSnapshot(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM afas_samenstellingen');
        if ($stmt === false) {
            return 0;
        }
        $count = $stmt->fetchColumn();

        return is_int($count) ? $count : 0;
    }
}
