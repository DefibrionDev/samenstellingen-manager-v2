<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Interface\Http;

use Defibrion\Samenstellingen\Application\Audit\AuditStickers;
use Defibrion\Samenstellingen\Application\Audit\StickerAuditHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListStickerDriftController
{
    public function __construct(private StickerAuditHandler $handler)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [];
        foreach (($this->handler)(new AuditStickers()) as $row) {
            $payload[] = [
                'groupName' => $row->groupName,
                'familyHeadItemcode' => $row->familyHeadItemcode,
                'baseName' => $row->baseName,
                'baseAfasItemcode' => $row->baseAfasItemcode,
                'languageCode' => $row->languageCode,
                'expectedSticker' => $row->expectedSticker,
                'actualStickers' => $row->actualStickers,
            ];
        }

        return Json::write($response, $payload);
    }
}
