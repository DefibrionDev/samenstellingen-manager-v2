<?php

declare(strict_types=1);

namespace Defibrion\Samenstellingen\Application\Woo;

use Defibrion\Samenstellingen\Domain\Woo\WooCommerceClient;
use Defibrion\Samenstellingen\Domain\Woo\WooCommerceStore;

/**
 * Bouwt een {@see WooCommerceClient} voor een specifieke store. Splitst de
 * domain-interface van de infra-bootstrap zodat tests een vaste client kunnen
 * injecteren via een In-memory factory.
 */
interface WooCommerceClientFactory
{
    public function forStore(WooCommerceStore $store): WooCommerceClient;
}
