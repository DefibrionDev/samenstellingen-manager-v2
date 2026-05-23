<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\AuditNames;
use Defibrion\Samenstellingen\Application\Audit\NameAuditHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListNameDriftController
{
    public function __construct(private NameAuditHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new AuditNames()) as $row) {
            $payload[] = [
                'afasItemcode' => $row->afasItemcode,
                'groupName' => $row->groupName,
                'familyHead' => $row->familyHead,
                'baseName' => $row->baseName,
                'languageCode' => $row->languageCode,
                'accessoireItemcode' => $row->accessoireItemcode,
                'accessoireLabel' => $row->accessoireLabel,
                'expected' => $row->expected,
                'actual' => $row->actual,
            ];
        }

        return Json::write($response, $payload);
    }
}
