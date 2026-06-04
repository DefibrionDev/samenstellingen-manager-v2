<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Defibrion\Samenstellingen\Domain\Woo\WooProductRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListWooStoresController
{
    public function __construct(
        private WooCommerceStoreRepository $stores,
        private WooProductRepository $products,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach ($this->stores->findAll() as $store) {
            $itemCount = 0;
            if ($store->id !== null) {
                $itemCount = count($this->products->findAllForStore($store->id));
            }
            $payload[] = [
                'id' => $store->id,
                'name' => $store->name,
                'baseUrl' => $store->baseUrl,
                'metaKey' => $store->afasItemcodeMetaKey,
                'itemCount' => $itemCount,
            ];
        }

        return Json::write($response, $payload);
    }
}
