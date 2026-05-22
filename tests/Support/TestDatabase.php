<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Support;

use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\Migrator;
use PDO;

final class TestDatabase
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = self::projectRoot() . '/tmp/test.sqlite';
            if (file_exists($dbPath)) {
                unlink($dbPath);
            }

            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA foreign_keys = ON');

            (new Migrator($pdo, self::projectRoot() . '/migrations'))->migrate();

            self::$pdo = $pdo;
        }

        return self::$pdo;
    }

    public static function truncate(): void
    {
        $pdo = self::pdo();
        $pdo->exec('DELETE FROM afas_samenstelling_bom');
        $pdo->exec('DELETE FROM afas_samenstellingen');
        $pdo->exec('DELETE FROM group_variants');
        $pdo->exec('DELETE FROM group_base_items');
        $pdo->exec('DELETE FROM group_accessoires');
        $pdo->exec('DELETE FROM accessoires');
        $pdo->exec('DELETE FROM group_bases');
        $pdo->exec('DELETE FROM groups');
        $pdo->exec(
            "DELETE FROM sqlite_sequence
             WHERE name IN ('groups', 'accessoires', 'group_variants', 'group_bases', 'afas_samenstellingen')"
        );
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
