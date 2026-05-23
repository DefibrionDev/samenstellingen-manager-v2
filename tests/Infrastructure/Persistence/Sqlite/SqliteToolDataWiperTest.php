<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Tests\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Accessoire\Accessoire;
use Defibrion\Samenstellingen\Domain\Group\Group;
use Defibrion\Samenstellingen\Domain\Group\GroupBase;
use Defibrion\Samenstellingen\Domain\Group\GroupBaseItem;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupAccessoireRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseItemRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupBaseRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteGroupRepository;
use Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite\SqliteToolDataWiper;
use Defibrion\Samenstellingen\Tests\Support\TestDatabase;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqliteToolDataWiperTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::truncate();
    }

    #[Test]
    public function wipesGroupsBasesItemsLinksAndVariantsButSparesAccessoiresCatalogue(): void
    {
        $pdo = TestDatabase::pdo();
        $groups = new SqliteGroupRepository($pdo);
        $bases = new SqliteGroupBaseRepository($pdo);
        $items = new SqliteGroupBaseItemRepository($pdo);
        $accessoires = new SqliteAccessoireRepository($pdo);
        $links = new SqliteGroupAccessoireRepository($pdo);

        $groups->save(new Group('Reanibex 100 Semi-Auto', '52112'));
        $accessoires->save(new Accessoire('60110', 'ARKY oranje buitenkast'));
        $accessoires->save(new Accessoire('60112', 'ARKY witte binnenkast'));
        $persistedBase = $bases->saveForGroup('52112', new GroupBase(null, 'AED pakket NL', 'NL'));
        self::assertNotNull($persistedBase->id);
        $items->saveForBase($persistedBase->id, new GroupBaseItem('50013', 'AED NL'));
        $links->link('52112', '60112');

        (new SqliteToolDataWiper($pdo))->wipe();

        self::assertSame(0, $this->countRows($pdo, 'groups'));
        self::assertSame(0, $this->countRows($pdo, 'group_bases'));
        self::assertSame(0, $this->countRows($pdo, 'group_base_items'));
        self::assertSame(0, $this->countRows($pdo, 'group_accessoires'));
        self::assertSame(0, $this->countRows($pdo, 'group_variants'));

        // Catalogus blijft intact — onze ground truth voor base/variant-detectie.
        self::assertSame(2, $this->countRows($pdo, 'accessoires'));
    }

    #[Test]
    public function leavesAfasSnapshotIntact(): void
    {
        $pdo = TestDatabase::pdo();
        $pdo->exec("INSERT INTO afas_samenstellingen (itemcode, name, itemcode_parent) VALUES ('70112', 'Reanimatiekit', NULL)");

        (new SqliteToolDataWiper($pdo))->wipe();

        self::assertSame(1, $this->countRows($pdo, 'afas_samenstellingen'));
    }

    #[Test]
    public function leavesBomBlacklistIntact(): void
    {
        $pdo = TestDatabase::pdo();
        $pdo->exec("INSERT INTO bom_blacklist (itemcode, reason) VALUES ('81311', 'Waalse stickerset')");

        (new SqliteToolDataWiper($pdo))->wipe();

        self::assertSame(1, $this->countRows($pdo, 'bom_blacklist'));
    }

    private function countRows(PDO $pdo, string $table): int
    {
        $stmt = $pdo->query(sprintf('SELECT COUNT(*) FROM %s', $table));
        self::assertNotFalse($stmt);

        return (int) $stmt->fetchColumn();
    }
}
