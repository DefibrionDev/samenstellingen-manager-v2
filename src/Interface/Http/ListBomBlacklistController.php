<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Afas\BomBlacklistRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListBomBlacklistController
{
    public function __construct(private BomBlacklistRepository $blacklist)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach ($this->blacklist->findAll() as $entry) {
            $payload[] = [
                'itemcode' => $entry->itemcode,
                'reason' => $entry->reason,
            ];
        }

        return Json::write($response, $payload);
    }
}
