<?php

/**
 * Plugin Name: AFAS checkout — factuurland uit AFAS
 * Description: Zet het factuurland van de WooCommerce-sessie gelijk aan het
 *              land uit de AFAS-factuurgegevens van de ingelogde klant.
 *
 * Waarom: lefcreative-afas-b2b vult de factuurvelden van de sessie alleen als
 * ze LEEG zijn (CheckoutAddressSelectorBlocks: `if (… && $customer->
 * get_billing_address_1() === '')`). Adres, postcode en plaats zijn in een
 * verse sessie inderdaad leeg en worden dus uit `billing_*`-usermeta gevuld,
 * maar het land niet: WooCommerce zet daar al het winkelland neer (hier NL).
 * Gevolg op een shop met buitenlandse klanten: factuurland NL naast een
 * buitenlandse postcode → "Billing Postcode / ZIP is not a valid postcode /
 * ZIP" en de klant komt niet door de checkout (defibsolutions.eu, livegang
 * 14 sep 2026, klant met Deens adres 8260 Viby J). Het verzendland stond wél
 * goed, want dat komt uit de AFAS-adreskiezer.
 *
 * Naast de blokkade corrigeert dit ook de btw-/verzendberekening: die gaat op
 * het factuurland van de sessie, dat anders ten onrechte NL zou zijn.
 *
 * De factuurvelden zijn op deze shops AFAS-gedreven en read-only, dus de
 * usermeta is hier per definitie de waarheid; er wordt geen keuze van de klant
 * overschreven. `save()` schrijft naar de sessie (data store
 * WC_Customer_Data_Store_Session), niet naar user_meta.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_loaded', static function (): void {
    if (!function_exists('WC')) {
        return;
    }

    $sync = static function (): void {
        if (!is_user_logged_in() || !WC()->customer instanceof WC_Customer) {
            return;
        }
        $land = strtoupper(trim((string) get_user_meta(get_current_user_id(), 'billing_country', true)));
        if ($land === '' || strtoupper((string) WC()->customer->get_billing_country()) === $land) {
            return;
        }
        WC()->customer->set_billing_country($land);
        WC()->customer->save();
    };

    // Vóór het renderen van de checkout (select toont dan het juiste land) …
    add_action('woocommerce_checkout_init', $sync, 5);
    // … vóór de validatie van de POST (postcode wordt tegen het juiste land getoetst) …
    add_action('woocommerce_before_checkout_process', $sync, 5);
    // … en op winkelwagen/checkout-load, zodat btw en verzending kloppen.
    add_action('wp', static function () use ($sync): void {
        if (function_exists('is_cart') && (is_cart() || is_checkout())) {
            $sync();
        }
    }, 5);
});
