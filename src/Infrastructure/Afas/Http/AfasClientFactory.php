<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Afas\Http;

use GuzzleHttp\Client;
use RuntimeException;

final class AfasClientFactory
{
    public static function fromEnv(): AfasHttpClient
    {
        $baseUrl = $_ENV['AFAS_BASE_URL'] ?? null;
        $token = $_ENV['AFAS_TOKEN'] ?? null;
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('AFAS_BASE_URL is niet gezet in env.');
        }
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('AFAS_TOKEN is niet gezet in env.');
        }

        return new AfasHttpClient(
            new Client(['timeout' => 60]),
            rtrim($baseUrl, '/'),
            $token,
        );
    }
}
