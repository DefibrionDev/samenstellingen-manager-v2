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

// Coupon-blok van bovenaan naar onder de totalen in de rechterkolom —
// zelfde aanpak als NL/reseller. Op wp_loaded omdat WooCommerce de hook
// pas tijdens plugin-load registreert.
add_action('wp_loaded', static function (): void {
    if (!function_exists('woocommerce_checkout_coupon_form')) {
        return;
    }
    remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);
    add_action('woocommerce_checkout_after_order_review', 'woocommerce_checkout_coupon_form', 10);
});

// Leeg "Détails de facturation"-blok verbergen (NL-aanpak): de plugin
// verbergt de gevulde billing-velden per rij (CheckoutReadonlyFields), maar
// de sectiekop bleef staan zodra er geen zichtbaar veld meer is. Bij een
// checkout_error toont de plugin de velden weer en komt de sectie terug.
add_action('wp_footer', static function (): void {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }
    ?>
    <script id="defibsfr-billing-wrap-toggle">
    (function () {
        function toggle() {
            var wrap = document.querySelector('.afas-checkout-cols .woocommerce-billing-fields');
            if (!wrap) { return; }
            var rows = wrap.querySelectorAll('.form-row');
            var zichtbaar = false;
            rows.forEach(function (r) { if (r.offsetParent !== null) { zichtbaar = true; } });
            wrap.style.display = (!rows.length || !zichtbaar) ? 'none' : '';
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

/* --- plugin-knoppen (+ Nouvelle adresse / Modifier / Opslaan): de plugin
       gebruikt Bootstrap-achtige .btn-classes die Woodmart niet kent —
       hier in de Woodmart-knopkleur (accent-var van het thema), met
       flex-centrering zodat de tekst in het midden van de knop staat --- */
.afas-checkout-cols .btn {
    /* géén flex: de plugin-JS toont de Modifier-knop met display:inline-block
       (inline style) — dan moeten beide knoppen in die modus gelijk renderen */
    display: inline-block;
    vertical-align: middle;
    text-align: center;
    background: var(--btn-accented-bgcolor, #83b735);
    color: var(--btn-accented-color, #fff);
    border: none;
    border-radius: 4px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.2;
    text-decoration: none;
    cursor: pointer;
    transition: opacity .15s;
}
.afas-checkout-cols .btn > span {
    display: block;
    line-height: 1.2;
}
.afas-checkout-cols .btn:hover {
    background: var(--btn-accented-bgcolor-hover, var(--btn-accented-bgcolor, #83b735));
    color: var(--btn-accented-color, #fff);
    opacity: .9;
}
.afas-checkout-cols .buttons-holder {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 12px;
}
.afas-checkout-cols .buttons-holder .btn + .btn {
    margin-left: 0; /* gap regelt de afstand */
}

/* --- placeholders: licht grijs (melding Cas: renderden zwart en leken
       al ingevuld) --- */
.afas-checkout-cols .input-text::placeholder,
.afas-checkout-cols input::placeholder,
.afas-checkout-cols textarea::placeholder {
    color: #9a9a9a !important;
    opacity: 1;
}

/* --- adresformulier: knoppenrij onder de floats van form-row-first/last --- */
.afas-checkout-address-selector #afas-checkout-address-form > p.form-row:last-child {
    clear: both;
}

/* --- coupon-melding: rustige balk met accentrand (NL-stijl). De extra
       specifieke toggle-selector wint van Woodmart's compacte notice-CSS
       (melding Cas: box zat te krap om de tekst) --- */
.afas-checkout-cols .woocommerce-form-coupon-toggle {
    margin: 20px 0 0;
}
.afas-checkout-cols .woocommerce-form-coupon-toggle .woocommerce-info,
.afas-checkout-cols .woocommerce-info {
    background: #fff !important;
    border: 1px solid #e2e2e2 !important;
    border-left: 3px solid var(--btn-accented-bgcolor, #83b735) !important;
    border-radius: 4px;
    padding: 16px 20px !important;
    margin: 16px 0;
    font-size: 14px;
    line-height: 1.6;
}
.afas-checkout-cols .woocommerce-form-coupon-toggle .woocommerce-info {
    margin: 0 !important;
}
.afas-checkout-cols .woocommerce-info::before {
    display: none !important;
}
.afas-checkout-cols .checkout_coupon {
    border: 1px solid #e2e2e2;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 16px;
}

/* --- betaalblok: grijs kader zoals NL --- */
.afas-checkout-cols #payment {
    background: #f7f7f7;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
}
.afas-checkout-cols #payment ul.payment_methods {
    padding: 16px 20px;
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
.afas-checkout-cols #payment div.payment_box {
    background: #fff;
    border-radius: 4px;
    padding: 12px 16px;
    margin-top: 8px;
    font-size: 14px;
}
.afas-checkout-cols #payment div.form-row.place-order {
    padding: 16px 20px;
    margin: 0;
}
.afas-checkout-cols .woocommerce-terms-and-conditions-wrapper {
    margin-bottom: 12px;
    font-size: 13px;
    line-height: 1.5;
}

/* --- mobiel: besteloverzicht bovenaan --- */
@media (max-width: 768px) {
    .afas-checkout-cols .afas-checkout-col-review { order: -1; }
    .afas-checkout-cols .afas-checkout-col-main   { order: 0; }
}
CSS;
    wp_register_style('defibsfr-checkout-restyle', false, [], '1.5');
    wp_enqueue_style('defibsfr-checkout-restyle');
    wp_add_inline_style('defibsfr-checkout-restyle', $css);
}, 20);
