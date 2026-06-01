<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Publications;

use Defibrion\Samenstellingen\Application\Publications\PublicationSyncFailedException;
use Defibrion\Samenstellingen\Application\Publications\PublicationSyncPlan;
use Defibrion\Samenstellingen\Application\Publications\PublicationSyncWriter;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * PUT FbComposition met @ItCd + Fields die de free-field flags zetten.
 * Voor base-samenstellingen én accessoire-varianten — beide zijn Type_item=7
 * in AFAS dus routen via FbComposition.
 */
final readonly class HttpPublicationSyncWriter implements PublicationSyncWriter
{
    public function __construct(private AfasHttpClient $client)
    {
    }

    public function apply(PublicationSyncPlan $plan): void
    {
        $fields = ['ItCd' => $plan->afasItemcode];
        foreach ($plan->freeFieldFlags as $uuid => $flag) {
            $fields[$uuid] = $flag;
        }
        $payload = [
            'FbComposition' => [
                'Element' => [
                    '@ItCd' => $plan->afasItemcode,
                    'Fields' => $fields,
                ],
            ],
        ];

        try {
            $this->client->updateConnector('FbComposition', $payload);
        } catch (Throwable $e) {
            throw PublicationSyncFailedException::from($plan, $e);
        }
    }
}
