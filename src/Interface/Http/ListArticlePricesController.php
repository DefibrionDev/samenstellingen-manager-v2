<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijsRepository;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListArticlePricesController
{
    public function __construct(private AfasPrijsRepository $prijzen)
    {
    }

    /**
     * @param array{itemcode: string} $args
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $itemcode = $args['itemcode'];
        $payload = [];
        foreach ($this->prijzen->findByItemcode($itemcode) as $p) {
            $payload[] = [
                'prijslijstId' => $p->prijslijstId,
                'debiteurId' => $p->debiteurId,
                'verkoopprijsCents' => $p->verkoopprijsCents,
                'verkoopprijsEur' => EuroParser::formatCents($p->verkoopprijsCents),
                'staffelAantal' => $p->staffelAantal,
                'geldigVan' => $p->geldigVan,
                'geldigTot' => $p->geldigTot,
            ];
        }

        return Json::write($response, $payload);
    }
}
