<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireAlreadyExistsException;
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
}
