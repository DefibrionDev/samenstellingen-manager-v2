<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\AuditSuspiciousBases;
use Defibrion\Samenstellingen\Application\Audit\SuspiciousBaseAuditHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListSuspiciousBasesController
{
    public function __construct(private SuspiciousBaseAuditHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new AuditSuspiciousBases()) as $row) {
            $payload[] = [
                'afasItemcode' => $row->afasItemcode,
                'name' => $row->name,
                'expectedAccessoireItemcode' => $row->expectedAccessoireItemcode,
                'expectedAccessoireLabel' => $row->expectedAccessoireLabel,
                'bom' => $row->bom,
            ];
        }

        return Json::write($response, $payload);
    }
}
