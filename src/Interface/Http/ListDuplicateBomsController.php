<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\AuditDuplicateBoms;
use Defibrion\Samenstellingen\Application\Audit\DuplicateBomAuditHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListDuplicateBomsController
{
    public function __construct(private DuplicateBomAuditHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new AuditDuplicateBoms()) as $group) {
            $payload[] = [
                'fingerprint' => $group->fingerprint,
                'memberCount' => count($group->members),
                'members' => $group->members,
            ];
        }

        return Json::write($response, $payload);
    }
}
