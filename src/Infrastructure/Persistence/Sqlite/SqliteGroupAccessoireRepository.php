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

    public function link(string $familyHeadItemcode, string $accessoireItemcode): void
    {
        $groupId = $this->resolveGroupId($familyHeadItemcode);
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
                    $familyHeadItemcode,
                );
            }
            throw $e;
        }
    }

    public function findAllForGroup(string $familyHeadItemcode): array
    {
        $groupId = $this->resolveGroupId($familyHeadItemcode);

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
            if (!is_array($row)) {
                continue;
            }
            $itemcode = $row['itemcode'] ?? null;
            $label = $row['label'] ?? null;
            if (is_string($itemcode) && is_string($label)) {
                $accessoires[] = new Accessoire($itemcode, $label);
            }
        }

        return $accessoires;
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
