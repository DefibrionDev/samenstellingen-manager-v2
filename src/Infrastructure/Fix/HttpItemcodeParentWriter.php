<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriteFailedException;
use Defibrion\Samenstellingen\Application\Fix\ItemcodeParentWriter;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * PUT FbComposition met Itemcode_Parent (vrije veld UUID
 * U298663A9447D4B4D8A0BB3FBC14A2C0B — zelfde veld dat
 * `afas-connector-tools/bin/set-itemcode-parent.php` gebruikt).
 */
final readonly class HttpItemcodeParentWriter implements ItemcodeParentWriter
{
    private const string FIELD_UUID = 'U298663A9447D4B4D8A0BB3FBC14A2C0B';

    public function __construct(private AfasHttpClient $client)
    {
    }

    public function write(string $itemcode, string $parent): void
    {
        try {
            $this->client->updateConnector('FbComposition', [
                'FbComposition' => [
                    'Element' => [
                        'Fields' => [
                            'ItCd' => $itemcode,
                            self::FIELD_UUID => $parent,
                        ],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            throw ItemcodeParentWriteFailedException::from($itemcode, $e);
        }
    }
}
