<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Persistence\Sqlite;

use Defibrion\Samenstellingen\Domain\Woo\WooProduct;
use Defibrion\Samenstellingen\Domain\Woo\WooProductRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProductVariation;
use PDO;

final readonly class SqliteWooProductRepository implements WooProductRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function replaceForStore(int $storeId, array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM woocommerce_products WHERE store_id = :store_id');
            $delete->execute([':store_id' => $storeId]);

            if ($items !== []) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO woocommerce_products
                        (store_id, wc_product_id, wc_parent_id, type, sku, afas_itemcode, name, status, permalink)
                     VALUES
                        (:store_id, :wc_id, :parent_id, :type, :sku, :afas_itemcode, :name, :status, :permalink)'
                );
                foreach ($items as $item) {
                    $insert->execute([
                        ':store_id' => $storeId,
                        ':wc_id' => $item->wcProductId,
                        ':parent_id' => $item instanceof WooProductVariation ? $item->parentId : null,
                        ':type' => $item instanceof WooProductVariation ? 'variation' : $item->type,
                        ':sku' => $item->sku,
                        ':afas_itemcode' => $item->afasItemcode,
                        ':name' => $item->name,
                        ':status' => $item->status,
                        ':permalink' => $item->permalink,
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findAllForStore(int $storeId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM woocommerce_products WHERE store_id = :store_id ORDER BY wc_product_id');
        $stmt->execute([':store_id' => $storeId]);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrate($row);
            }
        }

        return $items;
    }

    public function findByAfasItemcode(string $afasItemcode): array
    {
        if ($afasItemcode === '') {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM woocommerce_products
             WHERE afas_itemcode = :code
             ORDER BY store_id, wc_product_id'
        );
        $stmt->execute([':code' => $afasItemcode]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $result[] = [
                    'store_id' => (int) $row['store_id'],
                    'product' => $this->hydrate($row),
                ];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): WooProduct|WooProductVariation
    {
        $type = (string) $row['type'];
        $wcId = (int) $row['wc_product_id'];
        $sku = is_string($row['sku'] ?? null) ? $row['sku'] : null;
        $afasItemcode = is_string($row['afas_itemcode'] ?? null) ? $row['afas_itemcode'] : null;
        $name = (string) $row['name'];
        $status = (string) $row['status'];
        $permalink = is_string($row['permalink'] ?? null) ? $row['permalink'] : null;

        if ($type === 'variation') {
            return new WooProductVariation(
                $wcId,
                (int) $row['wc_parent_id'],
                $sku,
                $name,
                $status,
                $permalink,
                $afasItemcode,
            );
        }

        return new WooProduct(
            $wcId,
            $type,
            $sku,
            $name,
            $status,
            $permalink,
            $afasItemcode,
        );
    }
}
