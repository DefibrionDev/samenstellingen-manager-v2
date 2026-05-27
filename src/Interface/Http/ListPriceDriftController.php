<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\AuditPrices;
use Defibrion\Samenstellingen\Application\Audit\PriceAuditHandler;
use Defibrion\Samenstellingen\Domain\Money\EuroParser;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListPriceDriftController
{
    public function __construct(private PriceAuditHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new AuditPrices()) as $row) {
            $payload[] = [
                'groupName' => $row->groupName,
                'baseAfasItemcode' => $row->baseAfasItemcode,
                'baseName' => $row->baseName,
                'variantAfasItemcode' => $row->variantAfasItemcode,
                'accessoireItemcode' => $row->accessoireItemcode,
                'accessoireLabel' => $row->accessoireLabel,
                'expectedDeltaCents' => $row->expectedDeltaCents,
                'expectedDeltaEur' => EuroParser::formatCents($row->expectedDeltaCents),
                'prijslijstId' => $row->prijslijstId,
                'prijslijstOmschrijving' => $row->prijslijstOmschrijving,
                'basePrijsCents' => $row->basePrijsCents,
                'basePrijsEur' => EuroParser::formatCents($row->basePrijsCents),
                'variantPrijsCents' => $row->variantPrijsCents,
                'variantPrijsEur' => $row->variantPrijsCents !== null ? EuroParser::formatCents($row->variantPrijsCents) : null,
                'actualDeltaCents' => $row->actualDeltaCents,
                'actualDeltaEur' => $row->actualDeltaCents !== null ? EuroParser::formatCents($row->actualDeltaCents) : null,
                'status' => $row->status,
            ];
        }

        return Json::write($response, $payload);
    }
}
