<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Woo;

interface WooProductRepository
{
    /**
     * Vervang ALLE producten voor een gegeven store in één keer. Bestaande
     * rijen voor die store worden verwijderd, daarna worden de meegegeven
     * items bulk-geïnsert. Atomair vanuit caller-perspectief (transactie
     * in de implementatie).
     *
     * @param list<WooProduct|WooProductVariation> $items
     */
    public function replaceForStore(int $storeId, array $items): void;

    /**
     * @return list<WooProduct|WooProductVariation>
     */
    public function findAllForStore(int $storeId): array;

    /**
     * Alle producten + variations across-stores met dit AFAS-itemcode.
     * Volgorde: per store oplopend, daarbinnen op `wc_product_id`.
     *
     * @return list<array{store_id: int, product: WooProduct|WooProductVariation}>
     */
    public function findByAfasItemcode(string $afasItemcode): array;
}
