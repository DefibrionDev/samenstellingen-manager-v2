<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijs;
use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use PDO;

final readonly class SqliteAfasPrijsRepository implements AfasPrijsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function replaceSnapshot(array $prijzen): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM afas_prijzen');
            $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'afas_prijzen'");

            $stmt = $this->pdo->prepare(
                'INSERT INTO afas_prijzen
                    (itemcode, prijslijst_id, debiteur_id, verkoopprijs_cents, staffel_aantal, geldig_van, geldig_tot)
                 VALUES (:itemcode, :prijslijst_id, :debiteur_id, :verkoopprijs, :staffel, :van, :tot)'
            );
            foreach ($prijzen as $p) {
                $stmt->execute([
                    ':itemcode' => $p->itemcode,
                    ':prijslijst_id' => $p->prijslijstId,
                    ':debiteur_id' => $p->debiteurId,
                    ':verkoopprijs' => $p->verkoopprijsCents,
                    ':staffel' => $p->staffelAantal,
                    ':van' => $p->geldigVan,
                    ':tot' => $p->geldigTot,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findByItemcode(string $itemcode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT itemcode, prijslijst_id, debiteur_id, verkoopprijs_cents, staffel_aantal, geldig_van, geldig_tot
               FROM afas_prijzen WHERE itemcode = :itemcode ORDER BY prijslijst_id, staffel_aantal, geldig_van'
        );
        $stmt->execute([':itemcode' => $itemcode]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $result[] = new AfasPrijs(
                (string) $row['itemcode'],
                (string) $row['prijslijst_id'],
                isset($row['debiteur_id']) && is_string($row['debiteur_id']) ? $row['debiteur_id'] : null,
                (int) $row['verkoopprijs_cents'],
                isset($row['staffel_aantal']) && is_int($row['staffel_aantal']) ? $row['staffel_aantal'] : null,
                (string) $row['geldig_van'],
                isset($row['geldig_tot']) && is_string($row['geldig_tot']) ? $row['geldig_tot'] : null,
            );
        }

        return $result;
    }

    public function countSnapshot(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM afas_prijzen');
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }
}
