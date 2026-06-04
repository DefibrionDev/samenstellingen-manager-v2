<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Infrastructure\Woo\Http;

use Defibrion\Samenstellingen\Application\Woo\WooCommerceClientFactory;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceClient;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Maakt per store een fresh Guzzle-instance met de ck/cs in Basic-Auth en
 * base-URI op `/wp-json/wc/v3/`. Eén store = één client; geen sharing tussen
 * stores omdat auth + base-URI verschillen.
 */
final readonly class HttpWooCommerceClientFactory implements WooCommerceClientFactory
{
    public function forStore(WooCommerceStore $store): WooCommerceClient
    {
        $http = new GuzzleClient([
            'base_uri' => rtrim($store->baseUrl, '/') . '/wp-json/wc/v3/',
            'auth' => [$store->consumerKey, $store->consumerSecret],
            'timeout' => 60,
            'connect_timeout' => 10,
            'http_errors' => true,
        ]);

        return new HttpWooCommerceClient($http, $store->afasItemcodeMetaKey);
    }
}
