<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Publications\ListOnlineNotAssignedHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListOnlineNotAssignedController
{
    public function __construct(private ListOnlineNotAssignedHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)() as $row) {
            $payload[] = [
                'afasItemcode' => $row->afasItemcode,
                'baseAfasItemcode' => $row->baseAfasItemcode,
                'websiteName' => $row->websiteName,
            ];
        }

        return Json::write($response, $payload);
    }
}
