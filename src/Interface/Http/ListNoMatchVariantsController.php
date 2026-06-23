<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariants;
use Defibrion\Samenstellingen\Application\Audit\ListNoMatchVariantsHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListNoMatchVariantsController
{
    public function __construct(private ListNoMatchVariantsHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new ListNoMatchVariants()) as $row) {
            $payload[] = [
                'groupName' => $row->groep,
                'familyHead' => $row->familyHead,
                'baseName' => $row->baseNaam,
                'baseAfasSku' => $row->baseAfasSku,
                'accessoireItemcode' => $row->accessoireItemcode,
                'accessoireLabel' => $row->accessoireLabel,
                'expectedBom' => $row->verwachteBom,
                'verwachteItemcode' => $row->verwachteItemcode,
                'bestaandeAfasItemcode' => $row->bestaandeAfasItemcode,
                'exacteBomMatchItemcode' => $row->exacteBomMatchItemcode,
                'ontbrekendeItemcodes' => $row->ontbrekendeItemcodes,
                'extraItemcodes' => $row->extraItemcodes,
            ];
        }

        return Json::write($response, $payload);
    }
}
