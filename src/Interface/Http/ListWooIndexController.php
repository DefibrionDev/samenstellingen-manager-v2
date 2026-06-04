<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Woo\ListWooIndex;
use Defibrion\Samenstellingen\Application\Woo\ListWooIndexHandler;
use Defibrion\Samenstellingen\Application\Woo\WooIndexCell;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreNotFoundException;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStoreRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListWooIndexController
{
    public function __construct(
        private ListWooIndexHandler $handler,
        private WooCommerceStoreRepository $stores,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $storeName = isset($params['store']) && is_string($params['store']) && $params['store'] !== '' ? $params['store'] : null;
        $missingOnly = isset($params['missing']) && $params['missing'] === '1';

        try {
            $result = ($this->handler)(new ListWooIndex($storeName, $missingOnly, false));
        } catch (WooCommerceStoreNotFoundException $e) {
            return Json::write($response->withStatus(404), ['error' => $e->getMessage()]);
        }

        // Lijst van stores die in de output meekomen, in vaste volgorde voor de frontend.
        $storeMeta = [];
        if ($storeName === null) {
            foreach ($this->stores->findAll() as $store) {
                if ($store->id === null) {
                    continue;
                }
                $storeMeta[] = ['id' => $store->id, 'name' => $store->name];
            }
        } else {
            $store = $this->stores->findByName($storeName);
            if ($store !== null && $store->id !== null) {
                $storeMeta[] = ['id' => $store->id, 'name' => $store->name];
            }
        }

        $rows = [];
        foreach ($result->rows as $row) {
            $cells = [];
            foreach ($storeMeta as $store) {
                $cell = $row->cellsByStore[$store['id']] ?? null;
                $cells[] = [
                    'storeId' => $store['id'],
                    'storeName' => $store['name'],
                    'cell' => $cell === null ? null : $this->cellPayload($cell),
                ];
            }
            $rows[] = [
                'afasItemcode' => $row->afasItemcode,
                'cells' => $cells,
            ];
        }

        return Json::write($response, [
            'stores' => $storeMeta,
            'rows' => $rows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cellPayload(WooIndexCell $cell): array
    {
        return [
            'wcProductId' => $cell->wcProductId,
            'wcType' => $cell->wcType,
            'sku' => $cell->sku,
            'name' => $cell->name,
            'status' => $cell->status,
            'permalink' => $cell->permalink,
        ];
    }
}
