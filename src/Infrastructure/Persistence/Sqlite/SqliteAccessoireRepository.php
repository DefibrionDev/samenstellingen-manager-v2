<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireNotFoundException;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use PDO;
use PDOException;

final readonly class SqliteAccessoireRepository implements AccessoireRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Accessoire $accessoire): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO accessoires (itemcode, label) VALUES (:itemcode, :label)'
            );
            $stmt->execute([
                ':itemcode' => $accessoire->itemcode,
                ':label' => $accessoire->label,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed: accessoires.itemcode')) {
                throw AccessoireAlreadyExistsException::forItemcode($accessoire->itemcode);
            }
            throw $e;
        }
    }

    public function findByItemcode(string $itemcode): ?Accessoire
    {
        $stmt = $this->pdo->prepare(
            'SELECT itemcode, label FROM accessoires WHERE itemcode = :itemcode'
        );
        $stmt->execute([':itemcode' => $itemcode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $itemcodeValue = $row['itemcode'] ?? null;
        $labelValue = $row['label'] ?? null;
        if (!is_string($itemcodeValue) || !is_string($labelValue)) {
            return null;
        }

        return new Accessoire($itemcodeValue, $labelValue);
    }

    public function delete(string $itemcode): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM accessoires WHERE itemcode = :itemcode');
        $stmt->execute([':itemcode' => $itemcode]);
        if ($stmt->rowCount() === 0) {
            throw AccessoireNotFoundException::forItemcode($itemcode);
        }
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT itemcode, label FROM accessoires ORDER BY itemcode');
        if ($stmt === false) {
            return [];
        }

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itemcode = $row['itemcode'] ?? null;
            $label = $row['label'] ?? null;
            if (!is_string($itemcode) || !is_string($label)) {
                continue;
            }
            $result[] = new Accessoire($itemcode, $label);
        }

        return $result;
    }
}
