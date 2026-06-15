<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Domain\Afas;

/**
 * Haalt de webshop-producttypes (Product_type___01_/02_) op uit de PowerBI_Item
 * GetConnector — de enige connector die deze velden uitlevert (Get_Artikelen
 * niet). Levert alleen items met minstens één gevuld producttype. Zie
 * PLAN-AFAS.md §35.
 */
interface PowerBiItemFetcher
{
    /**
     * @return list<ItemProductTypes>
     */
    public function fetchProductTypes(): array;
}
