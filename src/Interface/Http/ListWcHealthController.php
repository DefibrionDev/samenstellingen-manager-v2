<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Woo\AuditWcHealth;
use Defibrion\Samenstellingen\Application\Woo\WcHealthAuditHandler;
use Defibrion\Samenstellingen\Application\Woo\WcHealthCell;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListWcHealthController
{
    public function __construct(
        private WcHealthAuditHandler $handler,
        private WooCommerceStoreRepository $stores,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $storeName = isset($params['store']) && is_string($params['store']) && $params['store'] !== '' ? $params['store'] : null;

        try {
            $rows = ($this->handler)(new AuditWcHealth($storeName));
        } catch (WooCommerceStoreNotFoundException $e) {
            return Json::write($response->withStatus(404), ['error' => $e->getMessage()]);
        }

        $storeMeta = [];
        if ($storeName === null) {
            foreach ($this->stores->findAll() as $store) {
                if ($store->id === null) {
                    continue;
                }
                $storeMeta[] = ['id' => $store->id, 'name' => $store->name];
            }
        } else {
            $single = $this->stores->findByName($storeName);
            if ($single !== null && $single->id !== null) {
                $storeMeta[] = ['id' => $single->id, 'name' => $single->name];
            }
        }

        $payloadRows = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($storeMeta as $store) {
                $cells[] = $this->cellPayload($store['id'], $store['name'], $row->cellsByStore[$store['id']] ?? null);
            }
            $payloadRows[] = [
                'afasItemcode' => $row->afasItemcode,
                'expectedType' => $row->expectedType,
                'cells' => $cells,
            ];
        }

        return Json::write($response, [
            'stores' => $storeMeta,
            'rows' => $payloadRows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cellPayload(int $storeId, string $storeName, ?WcHealthCell $cell): array
    {
        if ($cell === null) {
            return [
                'storeId' => $storeId,
                'storeName' => $storeName,
                'wcProductId' => null,
                'actualType' => null,
                'status' => null,
                'healthStatus' => 'missing',
            ];
        }

        return [
            'storeId' => $storeId,
            'storeName' => $storeName,
            'wcProductId' => $cell->wcProductId,
            'actualType' => $cell->actualType,
            'status' => $cell->status,
            'healthStatus' => $cell->healthStatus->value,
        ];
    }
}
