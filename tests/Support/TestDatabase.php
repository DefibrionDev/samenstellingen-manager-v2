<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Support;

use Defibrion\Samenstellingen\Bootstrap\Container;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\Migrator;
use PDO;

final class TestDatabase
{
    private static ?PDO $pdo = null;
    private static ?Container $container = null;

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
        $pdo->exec('DELETE FROM bom_blacklist');
        $pdo->exec('DELETE FROM afas_articles');
        $pdo->exec('DELETE FROM afas_prijzen');
        $pdo->exec('DELETE FROM afas_prijslijsten');
        $pdo->exec('DELETE FROM prijslijst_whitelist');
        $pdo->exec('DELETE FROM base_publications');
        $pdo->exec('DELETE FROM websites');
        $pdo->exec('DELETE FROM afas_free_field_state');
        $pdo->exec(
            "DELETE FROM sqlite_sequence
             WHERE name IN ('groups', 'accessoires', 'group_variants', 'group_bases', 'afas_samenstellingen', 'websites', 'base_publications')"
        );
    }

    public static function container(): Container
    {
        if (self::$container === null) {
            $pdo = self::pdo();
            $container = new class (self::projectRoot() . '/tmp/test.sqlite', self::projectRoot() . '/migrations', $pdo) extends Container {
                public function __construct(string $dbPath, string $migrationsDir, private PDO $injectedPdo)
                {
                    parent::__construct($dbPath, $migrationsDir);
                }

                public function pdo(): PDO
                {
                    return $this->injectedPdo;
                }
            };
            self::$container = $container;
        }

        return self::$container;
    }

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
