<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;

final readonly class RemoveWooStoreHandler
{
    public function __construct(private WooCommerceStoreRepository $repository)
    {
    }

    /**
     * Verwijder een store via z'n naam. Cascade in de DB ruimt z'n
     * `woocommerce_products`-rijen op. Returnt het id van de verwijderde
     * store voor logging/output-doeleinden.
     */
    public function __invoke(RemoveWooStore $command): int
    {
        $store = $this->repository->findByName($command->name);
        if ($store === null || $store->id === null) {
            throw WooCommerceStoreNotFoundException::forName($command->name);
        }
        $this->repository->delete($store->id);

        return $store->id;
    }
}
