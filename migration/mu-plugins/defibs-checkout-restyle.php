<?php
/**
 * Plugin Name: Defibs checkout-restyle
 * Description: Stylet de twee-koloms checkout-template van lefcreative-afas-b2b
 * naar de reseller-layout, in de DefibSolutions-huisstijl (groen #7CC68D, de
 * knoppen-/prijskleur van de site). Context: het divicommerce-childthema
 * stylet de OUDE checkout via de .checkout_v1-class op de Theme-Builder-sectie
 * (o.a. .col2-set width:60% !important) — die regels worden hier gericht
 * geneutraliseerd voor de nieuwe .afas-checkout-cols-markup.
 */

declare(strict_types=1);

// Coupon-blok (incl. de points-velden die points-and-rewards in
// checkout/form-coupon.php meebakt) van de linkerkolom naar onder de totalen
// in de rechterkolom — zelfde aanpak als reseller (checkout-coupon-points-right).
// Op wp_loaded omdat WooCommerce de hook pas tijdens plugin-load registreert.
add_action('wp_loaded', static function (): void {
    if (!function_exists('woocommerce_checkout_coupon_form')) {
        return;
    }
    remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
    add_action('woocommerce_checkout_after_order_review', 'woocommerce_checkout_coupon_form', 10);
});

// Leeg "Factuurgegevens"-blok verbergen: de plugin verbergt de gevulde
// billing-velden per rij (CheckoutReadonlyFields), maar de sectiekop + onze
// kaart eromheen bleven staan. Na elke (her)render checken of er nog een
// zichtbare rij is; zo niet, dan de hele sectie weg. Bij een checkout_error
// toont de plugin de velden weer en komt de sectie vanzelf terug.
add_action('wp_footer', static function (): void {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    ?>
    <script id="defibs-billing-wrap-toggle">
    (function () {
        function toggle() {
            var wrap = document.querySelector('.afas-checkout-cols .woocommerce-billing-fields');
            if (!wrap) { return; }
            var rows = wrap.querySelectorAll('.form-row');
            var zichtbaar = false;
            rows.forEach(function (r) { if (r.offsetParent !== null) { zichtbaar = true; } });
            wrap.style.display = (rows.length && !zichtbaar) ? 'none' : '';
        }
        var run = function () { setTimeout(toggle, 0); };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
        if (typeof jQuery !== 'undefined') {
            jQuery(document.body).on('updated_checkout checkout_error', run);
        }
    })();
    </script>
    <?php
}, 100);

add_action('wp_enqueue_scripts', static function (): void {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    $css = <<<'CSS'
/* --- bredere checkout: Divi-row is standaard 1080px --- */
.woocommerce-checkout .checkout_v1 .et_pb_row {
    max-width: 1320px;
    width: 94%;
}

/* --- childthema-regels voor de oude checkout neutraliseren --- */
.woocommerce-checkout .checkout_v1 .afas-checkout-cols .col2-set {
    float: none;
    width: 100% !important;
    margin-right: 0;
}
.woocommerce-checkout .checkout_v1 .afas-checkout-cols .woocommerce-checkout-review-order {
    float: none;
    width: 100%;
    background: transparent;
}

/* --- linkerkolom-secties: geen blokken (reseller-stijl), alleen ruimte --- */
.afas-checkout-cols .afas-invoice-summary,
.afas-checkout-cols .afas-checkout-address-selector,
.afas-checkout-cols .afas-checkout-custom-fields,
.afas-checkout-cols .woocommerce-billing-fields,
.afas-checkout-cols .woocommerce-shipping-fields,
.afas-checkout-cols .woocommerce-additional-fields {
    margin-bottom: 24px;
}

/* --- factuuradres: kop erboven, alleen het adres zelf in een kaderblok --- */
.afas-checkout-cols .afas-invoice-summary__address {
    display: block;
    padding: 1em 1.25em;
    border: 1px solid #e0e0e0;
    font-style: normal;
    line-height: 1.6;
    color: #333;
}
.afas-checkout-cols .afas-invoice-summary__name {
    display: block;
    font-weight: 600;
}

/* --- rechterkolom: geen blok (reseller-stijl), wel sticky --- */
.afas-checkout-cols .afas-checkout-col-review {
    position: sticky;
    top: 24px;
}

/* --- koppen --- */
.afas-checkout-cols h3,
.afas-checkout-cols #order_review_heading {
    font-size: 18px;
    font-weight: 700;
    color: #333;
    margin: 0 0 16px;
    padding: 0;
}

/* --- velden --- */
.afas-checkout-cols label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #333;
    margin-bottom: 4px;
}
.afas-checkout-cols .input-text,
.afas-checkout-cols select,
.afas-checkout-cols textarea {
    width: 100%;
    background: #fff;
    border: 1px solid #cecece;
    border-radius: 4px;
    padding: 10px 12px;
    font-size: 15px;
    color: #333;
    box-sizing: border-box;
}
.afas-checkout-cols .input-text:focus,
.afas-checkout-cols select:focus,
.afas-checkout-cols textarea:focus {
    border-color: #7CC68D;
    outline: none;
}
.afas-checkout-cols .form-row {
    margin: 0 0 14px;
    padding: 0;
}
.afas-checkout-cols abbr.required {
    color: #7CC68D;
    text-decoration: none;
    border: none;
}

/* --- knoppen --- */
.afas-checkout-cols .btn.bg-primary,
.afas-checkout-cols button.button,
.woocommerce-checkout .checkout_v1 .afas-checkout-cols #payment #place_order {
    display: inline-block;
    background: #7CC68D !important;
    color: #fff !important;
    border: none;
    border-radius: 4px;
    padding: 12px 24px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: background .15s;
}
.afas-checkout-cols .btn.bg-primary:hover,
.afas-checkout-cols button.button:hover,
.woocommerce-checkout .checkout_v1 .afas-checkout-cols #payment #place_order:hover {
    background: #5FA974 !important;
}
.woocommerce-checkout .checkout_v1 .afas-checkout-cols #payment #place_order {
    width: 100%;
    padding: 14px 24px;
    font-size: 16px;
}
.afas-checkout-cols .buttons-holder .btn + .btn {
    margin-left: 8px;
}
.afas-checkout-cols .afas-checkout-address-selector .buttons-holder {
    margin-top: 12px;
}

/* --- puntenblok: zelfde look als de waardebon-box. De site-custom-CSS
       (post 101114) zet hier een grijs vlak + blauwe knop op met !important
       en een id-selector; deze selectors zijn bewust specifieker. --- */
.afas-checkout-cols .custom_point_checkout.woocommerce-info.wps_wpr_checkout_points_class {
    background: #fff !important;
    border: 1px solid #e2e2e2 !important;
    border-left: 3px solid #7CC68D !important;
    color: #333 !important;
}
.afas-checkout-cols button#wps_cart_points_apply,
.afas-checkout-cols .wps_cart_points_apply {
    width: auto !important;
    display: inline-block;
    background: #7CC68D !important;
    color: #fff !important;
    border: none;
    border-radius: 4px;
    padding: 10px 20px;
}
.afas-checkout-cols button#wps_cart_points_apply:hover,
.afas-checkout-cols .wps_cart_points_apply:hover {
    background: #5FA974 !important;
    color: #fff !important;
}

/* --- bestellingsoverzicht-tabel (open, geen blok) --- */
.afas-checkout-cols table.shop_table {
    width: 100%;
    border: none;
    border-collapse: collapse;
    background: transparent;
}
.afas-checkout-cols table.shop_table th,
.afas-checkout-cols table.shop_table td {
    border: none;
    border-bottom: 1px solid #e2e2e2;
    padding: 10px 0;
    font-size: 14px !important;
    background: transparent;
    text-align: left;
}
.afas-checkout-cols table.shop_table td:last-child,
.afas-checkout-cols table.shop_table th + td {
    text-align: right;
}
.afas-checkout-cols table.shop_table tr.order-total th,
.afas-checkout-cols table.shop_table tr.order-total td {
    border-bottom: none;
    padding-top: 14px;
    font-size: 16px !important;
    font-weight: 700;
}

/* --- betaalblok --- */
.afas-checkout-cols #payment {
    border: 1px solid #cecece;
    border-radius: 6px;
}
.afas-checkout-cols #payment ul.payment_methods {
    padding: 16px 24px;
    border-bottom: 1px solid #e2e2e2;
}
.afas-checkout-cols #payment ul.payment_methods li {
    list-style: none;
    margin-bottom: 8px;
}
.afas-checkout-cols #payment ul.payment_methods label {
    display: inline;
    font-weight: 600;
}
.afas-checkout-cols #payment div.form-row.place-order {
    padding: 16px 24px;
}
.afas-checkout-cols #payment div.payment_box::before,
.afas-checkout-cols #payment div.payment_box::after {
    display: none !important;
}
.afas-checkout-cols #payment div.payment_box {
    background: #fff;
    border-radius: 4px;
    padding: 12px 16px;
    margin-top: 8px;
    font-size: 14px;
}

/* --- notices compact (waardebon-zin, punten-melding): reseller-stijl
       rustige balk; Divi zet er standaard een groot icoon en veel padding op --- */
.afas-checkout-cols .woocommerce-info {
    background: #fff !important;
    border: 1px solid #e2e2e2 !important;
    border-left: 3px solid #7CC68D !important;
    border-radius: 4px;
    color: #333 !important;
    padding: 12px 16px !important;
    margin: 0 0 16px;
    font-size: 14px;
    line-height: 1.5;
}
.afas-checkout-cols .woocommerce-info:before,
.afas-checkout-cols .woocommerce-info .et-pb-icon {
    display: none !important;
}
.afas-checkout-cols .woocommerce-info a {
    color: #5FA974;
    font-weight: 600;
    text-decoration: underline;
}

/* --- adresformulier: knoppenrij (Opslaan/Annuleren) onder de floats
       van form-row-first/last, anders knijpt hij in een reststrook --- */
.afas-checkout-address-selector #afas-checkout-address-form > p.form-row:last-child {
    clear: both;
}

/* --- mobiel: besteloverzicht bovenaan, daarna adres/facturatie --- */
@media (max-width: 768px) {
    .afas-checkout-cols .afas-checkout-col-review { order: -1; }
    .afas-checkout-cols .afas-checkout-col-main   { order: 0; }
}

/* --- puntenblok (points & rewards): volle breedte, invoer + knop op één regel --- */
.afas-checkout-cols .wps_wpr_shortcode_wrapper {
    width: 100%;
    max-width: none;
    margin: 0 0 16px;
}
.afas-checkout-cols .wps_wpr_shortcode_wrapper .input-text,
.afas-checkout-cols .wps_wpr_shortcode_wrapper input[type=number],
.afas-checkout-cols .wps_wpr_shortcode_wrapper input[type=text] {
    width: auto;
    min-width: 120px;
    display: inline-block;
    margin-right: 8px;
}
CSS;
    wp_register_style('defibs-checkout-restyle', false, [], '1.4');
    wp_enqueue_style('defibs-checkout-restyle');
    wp_add_inline_style('defibs-checkout-restyle', $css);
}, 20);
