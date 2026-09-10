<?php
/**
 * Plugin Name: DefibSolutions FR checkout-restyle
 * Description: Kleine opmaaklaag op de tweekoloms checkout-template van
 * lefcreative-afas-b2b voor de Woodmart-shop (boutique.defibsolutions.fr):
 * factuuradres in een kaderblok (zoals NL/reseller), naam vet, rustige
 * sectie-afstanden en een sticky besteloverzicht. Bewust minimaal: Woodmart
 * stylet velden en knoppen zelf al — geen kleuren of knop-overrides hier.
 */

declare(strict_types=1);

add_action('wp_enqueue_scripts', static function (): void {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    $css = <<<'CSS'
/* --- linkerkolom-secties: alleen ruimte, geen blokken --- */
.afas-checkout-cols .afas-invoice-summary,
.afas-checkout-cols .afas-checkout-address-selector,
.afas-checkout-cols .afas-checkout-custom-fields,
.afas-checkout-cols .woocommerce-billing-fields,
.afas-checkout-cols .woocommerce-shipping-fields,
.afas-checkout-cols .woocommerce-additional-fields {
    margin-bottom: 24px;
}

/* --- factuuradres: kop erboven, het adres zelf in een kaderblok --- */
.afas-checkout-cols .afas-invoice-summary__address {
    display: block;
    padding: 1em 1.25em;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-style: normal;
    line-height: 1.6;
}
.afas-checkout-cols .afas-invoice-summary__name {
    display: block;
    font-weight: 600;
}

/* --- rechterkolom sticky --- */
.afas-checkout-cols .afas-checkout-col-review {
    position: sticky;
    top: 24px;
}

/* --- adresformulier: knoppenrij onder de floats van form-row-first/last --- */
.afas-checkout-address-selector #afas-checkout-address-form > p.form-row:last-child {
    clear: both;
}

/* --- mobiel: besteloverzicht bovenaan --- */
@media (max-width: 768px) {
    .afas-checkout-cols .afas-checkout-col-review { order: -1; }
    .afas-checkout-cols .afas-checkout-col-main   { order: 0; }
}
CSS;
    wp_register_style('defibsfr-checkout-restyle', false, [], '1.0');
    wp_enqueue_style('defibsfr-checkout-restyle');
    wp_add_inline_style('defibsfr-checkout-restyle', $css);
}, 20);
