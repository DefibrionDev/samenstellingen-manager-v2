<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Group\GroupNotFoundException;
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
                'INSERT INTO groups (name, family_head_itemcode, model_name_nl, model_name_fr, model_name_en) VALUES (:name, :itemcode, :modelNl, :modelFr, :modelEn)'
            );
            $stmt->execute([
                ':name' => $group->name,
                ':itemcode' => $group->familyHeadItemcode,
                ':modelNl' => $group->modelNameNl,
                ':modelFr' => $group->modelNameFr,
                ':modelEn' => $group->modelNameEn,
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
            'SELECT name, family_head_itemcode, model_name_nl, model_name_fr, model_name_en FROM groups WHERE name = :name'
        );
        $stmt->execute([':name' => $name]);

        return $this->rowToGroup($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function findByFamilyHeadItemcode(string $familyHeadItemcode): ?Group
    {
        $stmt = $this->pdo->prepare(
            'SELECT name, family_head_itemcode, model_name_nl, model_name_fr, model_name_en FROM groups WHERE family_head_itemcode = :itemcode'
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

    public function updateModelNaam(string $familyHeadItemcode, string $taal, ?string $naam): void
    {
        $column = match ($taal) {
            'nl' => 'model_name_nl',
            'fr' => 'model_name_fr',
            'en' => 'model_name_en',
        };
        $stmt = $this->pdo->prepare("UPDATE groups SET $column = :naam WHERE family_head_itemcode = :itemcode");
        $stmt->execute([
            ':itemcode' => $familyHeadItemcode,
            ':naam' => $naam !== null && trim($naam) !== '' ? trim($naam) : null,
        ]);
        if ($stmt->rowCount() === 0 && $this->findByFamilyHeadItemcode($familyHeadItemcode) === null) {
            throw GroupNotFoundException::forFamilyHeadItemcode($familyHeadItemcode);
        }
    }

    public function updateFamilyHeadItemcode(string $oldFamilyHead, string $newFamilyHead): void
    {
        if ($this->findByFamilyHeadItemcode($oldFamilyHead) === null) {
            throw GroupNotFoundException::forFamilyHeadItemcode($oldFamilyHead);
        }
        if ($oldFamilyHead === $newFamilyHead) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE groups SET family_head_itemcode = :new WHERE family_head_itemcode = :old');
            $stmt->execute([':new' => $newFamilyHead, ':old' => $oldFamilyHead]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw GroupAlreadyExistsException::forFamilyHeadItemcode($newFamilyHead);
            }
            throw $e;
        }
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT name, family_head_itemcode, model_name_nl, model_name_fr, model_name_en FROM groups ORDER BY name');
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
        $modelNl = $row['model_name_nl'] ?? null;
        $modelFr = $row['model_name_fr'] ?? null;
        $modelEn = $row['model_name_en'] ?? null;

        return new Group(
            $name,
            $itemcode,
            is_string($modelNl) ? $modelNl : null,
            is_string($modelFr) ? $modelFr : null,
            is_string($modelEn) ? $modelEn : null,
        );
    }
}
