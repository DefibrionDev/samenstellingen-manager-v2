<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\BasePriceGapsHandler;
use Defibrion\Samenstellingen\Application\Audit\ListBasePriceGaps;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListBasePriceGapsController
{
    public function __construct(private BasePriceGapsHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new ListBasePriceGaps()) as $row) {
            $payload[] = [
                'prijslijstId' => $row->prijslijstId,
                'prijslijstOmschrijving' => $row->prijslijstOmschrijving,
                'baseAfasItemcode' => $row->baseAfasItemcode,
                'groupName' => $row->groupName,
                'baseName' => $row->baseName,
            ];
        }

        return Json::write($response, $payload);
    }
}
