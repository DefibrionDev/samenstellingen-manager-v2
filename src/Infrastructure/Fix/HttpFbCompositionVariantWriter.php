<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Fix;

use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingFailedException;
use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingPlan;
use Defibrion\Samenstellingen\Application\Fix\VariantFixMissingWriter;
use Defibrion\Samenstellingen\Application\Fix\VariantWriteContextLookup;
use Defibrion\Samenstellingen\Infrastructure\Afas\Http\AfasHttpClient;
use Throwable;

/**
 * POST nieuwe variant naar AFAS via UpdateConnector FbComposition.
 * Payload-shape bewezen in PoC 39.0 (zie PLAN.md §20).
 */
final readonly class HttpFbCompositionVariantWriter implements VariantFixMissingWriter
{
    public function __construct(
        private AfasHttpClient $client,
        private VariantWriteContextLookup $lookup,
        private FbCompositionVariantPayloadBuilder $payloadBuilder,
    ) {
    }

    public function apply(VariantFixMissingPlan $plan): void
    {
        $payload = $this->payloadBuilder->build($plan, $this->lookup);

        try {
            $this->client->insertConnector('FbComposition', $payload);
        } catch (Throwable $e) {
            throw VariantFixMissingFailedException::from($plan, $e);
        }
    }
}
