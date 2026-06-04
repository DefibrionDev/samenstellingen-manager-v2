<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Woo\ListWooIndex;
use Defibrion\Samenstellingen\Application\Woo\ListWooIndexHandler;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListWooOrphansController
{
    public function __construct(private ListWooIndexHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $storeName = isset($params['store']) && is_string($params['store']) && $params['store'] !== '' ? $params['store'] : null;

        try {
            $result = ($this->handler)(new ListWooIndex($storeName, false, true));
        } catch (WooCommerceStoreNotFoundException $e) {
            return Json::write($response->withStatus(404), ['error' => $e->getMessage()]);
        }

        $payload = [];
        foreach ($result->orphans as $orphan) {
            $payload[] = [
                'storeId' => $orphan->storeId,
                'storeName' => $orphan->storeName,
                'wcProductId' => $orphan->wcProductId,
                'wcType' => $orphan->wcType,
                'sku' => $orphan->sku,
                'name' => $orphan->name,
                'status' => $orphan->status,
                'afasItemcode' => $orphan->afasItemcode,
                'permalink' => $orphan->permalink,
            ];
        }

        return Json::write($response, $payload);
    }
}
