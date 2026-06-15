<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

interface AfasSamenstellingenRepository
{
    /**
     * Vervang de hele lokale snapshot atomisch. Detecteert intern duplicates (identieke BOMs):
     * laagste itemcode wordt canonical, rest krijgt `duplicateOfItemcode` ingevuld.
     *
     * @param list<AfasSamenstelling> $samenstellingen
     */
    public function replaceSnapshot(array $samenstellingen): void;

    /**
     * Werk de webshop-producttypes (Product_type___01_/02_) bij op bestaande
     * samenstelling-rijen, gematcht op itemcode. Itemcodes die niet als
     * samenstelling in de snapshot staan worden genegeerd (geen insert).
     * Aparte pass na replaceSnapshot omdat de descriptions uit een andere
     * GetConnector (PowerBI_Item) komen. Zie PLAN-AFAS.md §35.
     *
     * @param list<ItemProductTypes> $productTypes
     */
    public function updateProductTypes(array $productTypes): void;

    /**
     * @return list<AfasSamenstelling>
     */
    public function findAll(): array;

    /**
     * Alleen canonicals (duplicate_of_itemcode IS NULL).
     *
     * @return list<AfasSamenstelling>
     */
    public function findAllCanonical(): array;

    /**
     * Alleen duplicates (duplicate_of_itemcode IS NOT NULL).
     *
     * @return list<AfasSamenstelling>
     */
    public function findAllDuplicates(): array;

    public function countSnapshot(): int;

    public function findByItemcode(string $itemcode): ?AfasSamenstelling;
}
