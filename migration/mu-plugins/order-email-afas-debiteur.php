<?php
/**
 * Plugin Name: Order e-mails: AFAS-debiteurnummer (mu)
 * Description: Toont het AFAS-debiteurnummer van de klant klein en grijs in
 *              alle ordermails (onder de ordergegevens), zodat collega's bij
 *              een klantmail direct zien welk debiteurnummer erbij hoort.
 *              Toont niets als de klant (nog) geen AFAS-koppeling heeft.
 * Version: 1.0
 */

defined('ABSPATH') || exit;

add_action('woocommerce_email_order_meta', static function ($order, $sent_to_admin = false, $plain_text = false) {
    if (!$order instanceof WC_Order) {
        return;
    }

    // Ordermeta eerst (gezet door de AFAS-plugin bij de push), anders de
    // koppeling op het klantaccount.
    $relatieId = (string) $order->get_meta('_afas_relatie_id');
    if ('' === $relatieId && $order->get_customer_id()) {
        $relatieId = (string) get_user_meta($order->get_customer_id(), 'afas_relatie_id', true);
    }
    if ('' === $relatieId) {
        return;
    }

    // E-mails renderen in de taal van de klant/bestelling.
    $locale = determine_locale();
    $label = str_starts_with($locale, 'nl') ? 'Klantnummer'
        : (str_starts_with($locale, 'fr') ? 'Numéro de client' : 'Customer number');
    $tekst = sprintf('(%s: %s)', $label, $relatieId);

    if ($plain_text) {
        echo "\n", esc_html($tekst), "\n";
        return;
    }

    echo '<p style="font-size:11px; color:#999999; margin:0 0 16px;">' . esc_html($tekst) . '</p>';
}, 10, 3);
