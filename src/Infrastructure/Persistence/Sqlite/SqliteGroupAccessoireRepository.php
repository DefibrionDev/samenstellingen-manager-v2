<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Group\AccessoireAlreadyLinkedException;
use Defibrion\Samenstellingen\Domain\Group\GroupAccessoireRepository;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
use PDO;
use PDOException;

final readonly class SqliteGroupAccessoireRepository implements GroupAccessoireRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function link(string $groupName, string $accessoireItemcode): void
    {
        $groupId = $this->resolveGroupId($groupName);
        $accessoireId = $this->resolveAccessoireId($accessoireItemcode);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO group_accessoires (group_id, accessoire_id)
                 VALUES (:group_id, :accessoire_id)'
            );
            $stmt->execute([
                ':group_id' => $groupId,
                ':accessoire_id' => $accessoireId,
            ]);
        } catch (PDOException $e) {
            if (
                str_contains($e->getMessage(), 'UNIQUE constraint failed')
                && str_contains($e->getMessage(), 'group_accessoires')
            ) {
                throw AccessoireAlreadyLinkedException::forAccessoireInGroup(
                    $accessoireItemcode,
                    $groupName,
                );
            }
            throw $e;
        }
    }

    public function findAllForGroup(string $groupName): array
    {
        $groupId = $this->resolveGroupId($groupName);

        $stmt = $this->pdo->prepare(
            'SELECT a.itemcode, a.label
             FROM accessoires a
             INNER JOIN group_accessoires ga ON ga.accessoire_id = a.id
             WHERE ga.group_id = :group_id
             ORDER BY a.itemcode'
        );
        $stmt->execute([':group_id' => $groupId]);

        $accessoires = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (
                is_array($row)
                && is_string($row['itemcode'] ?? null)
                && is_string($row['label'] ?? null)
            ) {
                $accessoires[] = new Accessoire($row['itemcode'], $row['label']);
            }
        }

        return $accessoires;
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

    private function resolveAccessoireId(string $itemcode): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM accessoires WHERE itemcode = :itemcode');
        $stmt->execute([':itemcode' => $itemcode]);
        $id = $stmt->fetchColumn();
        if (!is_int($id)) {
            throw AccessoireNotFoundException::forItemcode($itemcode);
        }

        return $id;
    }
}
