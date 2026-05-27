<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\PrijslijstAlreadyBlacklistedException;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistEntry;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstNotBlacklistedException;
use PDO;
use PDOException;

final readonly class SqlitePrijslijstBlacklistRepository implements PrijslijstBlacklistRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function add(string $prijslijstId, string $reden): void
    {
        // VO-constructie valideert lege id/reden vóór we de DB raken.
        $entry = new PrijslijstBlacklistEntry($prijslijstId, $reden);
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO prijslijst_blacklist (prijslijst_id, reden) VALUES (:id, :reden)'
            );
            $stmt->execute([':id' => $entry->prijslijstId, ':reden' => $entry->reden]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed: prijslijst_blacklist.prijslijst_id')) {
                throw PrijslijstAlreadyBlacklistedException::forId($entry->prijslijstId);
            }
            throw $e;
        }
    }

    public function remove(string $prijslijstId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM prijslijst_blacklist WHERE prijslijst_id = :id');
        $stmt->execute([':id' => $prijslijstId]);
        if ($stmt->rowCount() === 0) {
            throw PrijslijstNotBlacklistedException::forId($prijslijstId);
        }
    }

    public function isBlacklisted(string $prijslijstId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM prijslijst_blacklist WHERE prijslijst_id = :id');
        $stmt->execute([':id' => $prijslijstId]);

        return $stmt->fetchColumn() !== false;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT prijslijst_id, reden, aangemaakt_op FROM prijslijst_blacklist ORDER BY prijslijst_id'
        );
        if ($stmt === false) {
            return [];
        }

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['prijslijst_id'] ?? null;
            $reden = $row['reden'] ?? null;
            $aangemaakt = $row['aangemaakt_op'] ?? null;
            if (!is_string($id) || !is_string($reden)) {
                continue;
            }
            $result[] = new PrijslijstBlacklistEntry(
                $id,
                $reden,
                is_string($aangemaakt) ? $aangemaakt : null,
            );
        }

        return $result;
    }
}
