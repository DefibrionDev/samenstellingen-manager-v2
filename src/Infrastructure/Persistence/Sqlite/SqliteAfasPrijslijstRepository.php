<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijst;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use PDO;

final readonly class SqliteAfasPrijslijstRepository implements AfasPrijslijstRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function replaceSnapshot(array $prijslijsten): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM afas_prijslijsten');

            $stmt = $this->pdo->prepare(
                'INSERT INTO afas_prijslijsten (id, omschrijving) VALUES (:id, :omschrijving)'
            );
            foreach ($prijslijsten as $p) {
                $stmt->execute([
                    ':id' => $p->id,
                    ':omschrijving' => $p->omschrijving,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, omschrijving FROM afas_prijslijsten ORDER BY id');
        if ($stmt === false) {
            return [];
        }

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $result[] = new AfasPrijslijst((string) $row['id'], (string) $row['omschrijving']);
        }

        return $result;
    }

    public function findById(string $id): ?AfasPrijslijst
    {
        $stmt = $this->pdo->prepare('SELECT id, omschrijving FROM afas_prijslijsten WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return new AfasPrijslijst((string) $row['id'], (string) $row['omschrijving']);
    }

    public function countSnapshot(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM afas_prijslijsten');
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }
}
