<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Afas\AfasPrijslijstRepository;
use Defibrion\Samenstellingen\Domain\Afas\PrijslijstBlacklistRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListPrijslijstBlacklistController
{
    public function __construct(
        private PrijslijstBlacklistRepository $blacklist,
        private AfasPrijslijstRepository $prijslijsten,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach ($this->blacklist->findAll() as $entry) {
            $prijslijst = $this->prijslijsten->findById($entry->prijslijstId);
            $payload[] = [
                'prijslijstId' => $entry->prijslijstId,
                'omschrijving' => $prijslijst !== null ? $prijslijst->omschrijving : null,
                'reden' => $entry->reden,
                'aangemaaktOp' => $entry->aangemaaktOp,
            ];
        }

        return Json::write($response, $payload);
    }
}
