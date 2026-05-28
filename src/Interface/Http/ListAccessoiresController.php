<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Accessoire\AccessoireRepository;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListAccessoiresController
{
    public function __construct(private AccessoireRepository $accessoires)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach ($this->accessoires->findAll() as $accessoire) {
            $payload[] = [
                'itemcode' => $accessoire->itemcode,
                'label' => $accessoire->label,
                'deltaCents' => $accessoire->deltaCents,
                'deltaEur' => EuroParser::formatCents($accessoire->deltaCents),
                'naamKortNl' => $accessoire->naamKortNl,
                'naamKortFr' => $accessoire->naamKortFr,
                'naamKortEn' => $accessoire->naamKortEn,
            ];
        }
        usort($payload, static fn ($a, $b) => strcmp($a['itemcode'], $b['itemcode']));

        return Json::write($response, $payload);
    }
}
