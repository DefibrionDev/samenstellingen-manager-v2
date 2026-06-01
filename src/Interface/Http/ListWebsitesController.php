<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Domain\Website\WebsiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListWebsitesController
{
    public function __construct(private WebsiteRepository $websites)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach ($this->websites->findAll() as $website) {
            $payload[] = [
                'id' => $website->id,
                'name' => $website->name,
                'ffSyncUuid' => $website->ffSyncUuid,
                'ffTonenUuid' => $website->ffTonenUuid,
            ];
        }

        return Json::write($response, $payload);
    }
}
