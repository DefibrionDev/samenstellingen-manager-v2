<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Bootstrap;

use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\Migrator;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasArticleRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasPrijslijstRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasPrijsRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAfasSamenstellingenRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteBomBlacklistRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupVariantRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqlitePrijslijstBlacklistRepository;
use PDO;

/**
 * Lightweight container — CLI en HTTP front-controller delen dezelfde repo-construeer-code,
 * zodat een wijziging in de wiring op één plek staat.
 */
class Container
{
    private ?PDO $pdo = null;
    private ?SqliteGroupRepository $groupRepository = null;
    private ?SqliteGroupBaseRepository $baseRepository = null;
    private ?SqliteGroupBaseItemRepository $baseItemRepository = null;
    private ?SqliteAccessoireRepository $accessoireRepository = null;
    private ?SqliteGroupAccessoireRepository $linkRepository = null;
    private ?SqliteGroupVariantRepository $variantRepository = null;
    private ?SqliteAfasSamenstellingenRepository $afasSamenstellingenRepository = null;
    private ?SqliteAfasArticleRepository $afasArticleRepository = null;
    private ?SqliteAfasPrijsRepository $afasPrijsRepository = null;
    private ?SqliteAfasPrijslijstRepository $afasPrijslijstRepository = null;
    private ?SqlitePrijslijstBlacklistRepository $prijslijstBlacklistRepository = null;
    private ?SqliteBomBlacklistRepository $bomBlacklistRepository = null;

    public function __construct(
        private readonly string $dbPath,
        private readonly string $migrationsDir,
    ) {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dbDir = dirname($this->dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0o755, true);
            }
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            (new Migrator($this->pdo, $this->migrationsDir))->migrate();
        }

        return $this->pdo;
    }

    public function groupRepository(): SqliteGroupRepository
    {
        return $this->groupRepository ??= new SqliteGroupRepository($this->pdo());
    }

    public function baseRepository(): SqliteGroupBaseRepository
    {
        return $this->baseRepository ??= new SqliteGroupBaseRepository($this->pdo());
    }

    public function baseItemRepository(): SqliteGroupBaseItemRepository
    {
        return $this->baseItemRepository ??= new SqliteGroupBaseItemRepository($this->pdo());
    }

    public function accessoireRepository(): SqliteAccessoireRepository
    {
        return $this->accessoireRepository ??= new SqliteAccessoireRepository($this->pdo());
    }

    public function linkRepository(): SqliteGroupAccessoireRepository
    {
        return $this->linkRepository ??= new SqliteGroupAccessoireRepository($this->pdo());
    }

    public function variantRepository(): SqliteGroupVariantRepository
    {
        return $this->variantRepository ??= new SqliteGroupVariantRepository($this->pdo());
    }

    public function afasSamenstellingenRepository(): SqliteAfasSamenstellingenRepository
    {
        return $this->afasSamenstellingenRepository ??= new SqliteAfasSamenstellingenRepository($this->pdo());
    }

    public function bomBlacklistRepository(): SqliteBomBlacklistRepository
    {
        return $this->bomBlacklistRepository ??= new SqliteBomBlacklistRepository($this->pdo());
    }

    public function afasArticleRepository(): SqliteAfasArticleRepository
    {
        return $this->afasArticleRepository ??= new SqliteAfasArticleRepository($this->pdo());
    }

    public function afasPrijsRepository(): SqliteAfasPrijsRepository
    {
        return $this->afasPrijsRepository ??= new SqliteAfasPrijsRepository($this->pdo());
    }

    public function afasPrijslijstRepository(): SqliteAfasPrijslijstRepository
    {
        return $this->afasPrijslijstRepository ??= new SqliteAfasPrijslijstRepository($this->pdo());
    }

    public function prijslijstBlacklistRepository(): SqlitePrijslijstBlacklistRepository
    {
        return $this->prijslijstBlacklistRepository ??= new SqlitePrijslijstBlacklistRepository($this->pdo());
    }
}
