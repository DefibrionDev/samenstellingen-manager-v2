<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\ListMissingVariants;
use Defibrion\Samenstellingen\Application\Audit\ListMissingVariantsHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListMissingVariantsController
{
    public function __construct(private ListMissingVariantsHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new ListMissingVariants()) as $row) {
            $payload[] = [
                'groupName' => $row->groep,
                'baseName' => $row->baseNaam,
                'baseAfasSku' => $row->baseAfasSku,
                'accessoireItemcode' => $row->accessoireItemcode,
                'accessoireLabel' => $row->accessoireLabel,
                'expectedBom' => $row->verwachteBom,
                'suggestedSku' => $row->verwachteSkuVoorstel,
            ];
        }

        return Json::write($response, $payload);
    }
}
