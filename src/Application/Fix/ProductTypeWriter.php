<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Fix;

/**
 * Schrijft de webshop-producttypes (Product_type___01_/02_) op een
 * samenstelling in AFAS. Parameters zijn descriptions ("AED pakket" / "350P");
 * de implementatie resolvet ze naar de AFAS enum-id's. Zie PLAN-AFAS.md §35.
 */
interface ProductTypeWriter
{
    /**
     * @throws ProductTypeWriteFailedException
     */
    public function write(string $itemcode, string $productType01, string $productType02): void;
}
