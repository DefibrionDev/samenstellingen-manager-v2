<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use PDO;

final readonly class SqliteWooCommerceStoreRepository implements WooCommerceStoreRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(WooCommerceStore $store): WooCommerceStore
    {
        if ($store->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO woocommerce_stores (name, base_url, consumer_key, consumer_secret, afas_itemcode_meta_key)
                 VALUES (:name, :base_url, :ck, :cs, :meta_key)'
            );
            $stmt->execute([
                ':name' => $store->name,
                ':base_url' => $store->baseUrl,
                ':ck' => $store->consumerKey,
                ':cs' => $store->consumerSecret,
                ':meta_key' => $store->afasItemcodeMetaKey,
            ]);
            $id = (int) $this->pdo->lastInsertId();
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE woocommerce_stores
                 SET name = :name, base_url = :base_url, consumer_key = :ck,
                     consumer_secret = :cs, afas_itemcode_meta_key = :meta_key
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $store->id,
                ':name' => $store->name,
                ':base_url' => $store->baseUrl,
                ':ck' => $store->consumerKey,
                ':cs' => $store->consumerSecret,
                ':meta_key' => $store->afasItemcodeMetaKey,
            ]);
            $id = $store->id;
        }

        return new WooCommerceStore(
            $id,
            $store->name,
            $store->baseUrl,
            $store->consumerKey,
            $store->consumerSecret,
            $store->afasItemcodeMetaKey,
        );
    }

    public function findByName(string $name): ?WooCommerceStore
    {
        $stmt = $this->pdo->prepare('SELECT * FROM woocommerce_stores WHERE name = :name');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM woocommerce_stores ORDER BY name');
        if ($stmt === false) {
            return [];
        }

        $stores = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $stores[] = $this->hydrate($row);
            }
        }

        return $stores;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM woocommerce_stores WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw WooCommerceStoreNotFoundException::forName('id=' . $id);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): WooCommerceStore
    {
        return new WooCommerceStore(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['base_url'],
            (string) $row['consumer_key'],
            (string) $row['consumer_secret'],
            (string) $row['afas_itemcode_meta_key'],
        );
    }
}
