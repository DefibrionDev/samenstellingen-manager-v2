<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Afas\BomCodeAlreadyBlacklistedException;
use PDO;
use PDOException;

final readonly class SqliteBomBlacklistRepository implements BomBlacklistRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(BomBlacklistEntry $entry): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO bom_blacklist (itemcode, reason) VALUES (:itemcode, :reason)'
            );
            $stmt->execute([
                ':itemcode' => $entry->itemcode,
                ':reason' => $entry->reason,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed: bom_blacklist.itemcode')) {
                throw BomCodeAlreadyBlacklistedException::forItemcode($entry->itemcode);
            }
            throw $e;
        }
    }

    public function findByItemcode(string $itemcode): ?BomBlacklistEntry
    {
        $stmt = $this->pdo->prepare(
            'SELECT itemcode, reason FROM bom_blacklist WHERE itemcode = :itemcode'
        );
        $stmt->execute([':itemcode' => $itemcode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $itemcodeValue = $row['itemcode'] ?? null;
        $reasonValue = $row['reason'] ?? null;
        if (!is_string($itemcodeValue) || !is_string($reasonValue)) {
            return null;
        }

        return new BomBlacklistEntry($itemcodeValue, $reasonValue);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT itemcode, reason FROM bom_blacklist ORDER BY itemcode');
        if ($stmt === false) {
            return [];
        }

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $itemcode = $row['itemcode'] ?? null;
            $reason = $row['reason'] ?? null;
            if (!is_string($itemcode) || !is_string($reason)) {
                continue;
            }
            $result[] = new BomBlacklistEntry($itemcode, $reason);
        }

        return $result;
    }
}
