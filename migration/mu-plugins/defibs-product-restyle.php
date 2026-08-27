<?php
/**
 * Plugin Name: Defibs product-restyle
 * Description: Stylet de variatie-picker op productpagina's (taal-knoppen van
 * woo-variation-swatches + dropdowns) naar de reseller-look, in de
 * DefibSolutions-huisstijl (groen #7CC68D). Divi laat deze elementen kaal;
 * zelfde aanpak als defibs-checkout-restyle.
 */

declare(strict_types=1);

add_action('wp_enqueue_scripts', static function (): void {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }
    $css = <<<'CSS'
/* --- variatie-tabel: labels als kopjes boven hun keuze, rustige rijen --- */
.single-product form.variations_form table.variations,
.single-product form.variations_form table.variations tbody,
.single-product form.variations_form table.variations tr,
.single-product form.variations_form table.variations th,
.single-product form.variations_form table.variations td {
    display: block;
    width: 100%;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    background: transparent;
    text-align: left;
}
.single-product form.variations_form table.variations tr {
    margin: 0 0 8px !important;
}
.single-product form.variations_form table.variations th.label label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    color: #333;
    margin: 0 0 4px !important;
    padding: 0 !important;
}

/* --- dropdowns (zichtbare selects, bv. Opties) --- */
.single-product form.variations_form table.variations select {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border: 1px solid #cecece;
    border-radius: 4px;
    padding: 10px 12px;
    font-size: 15px;
    color: #333;
}
.single-product form.variations_form table.variations select:focus {
    border-color: #7CC68D;
    outline: none;
}

/* --- taal-knoppen (woo-variation-swatches) --- */
.single-product .variable-items-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    /* Divi geeft ul's extra padding en de swatches-plugin geeft li's eigen
       marges — beide hard op 0, anders stapelt het op de flex-gap */
    margin: 0 !important;
    padding: 0 !important;
}
.single-product .variable-items-wrapper .variable-item {
    margin: 0 !important;
}
.single-product .variable-items-wrapper .variable-item.button-variable-item {
    background: #fff;
    border: 1px solid #cecece;
    border-radius: 4px;
    box-shadow: none;
    padding: 6px 12px;
    height: auto;
    min-width: 0;
    font-size: 14px;
    line-height: 1.4;
    letter-spacing: normal;
    color: #333;
    transition: border-color .15s, background .15s;
}
.single-product .variable-items-wrapper .variable-item .variable-item-contents,
.single-product .variable-items-wrapper .variable-item .variable-item-span {
    padding: 0;
    margin: 0;
    letter-spacing: normal;
}
.single-product .variable-items-wrapper .variable-item.button-variable-item:hover {
    border-color: #7CC68D;
    box-shadow: none;
}
.single-product .variable-items-wrapper .variable-item.button-variable-item.selected,
.single-product .variable-items-wrapper .variable-item.button-variable-item.selected:hover {
    border: 2px solid #7CC68D;
    background: #f2faf4;
    box-shadow: none;
    padding: 5px 11px; /* compenseert de dikkere rand */
}

/* --- Divi Theme-Builder-marges rond de picker: de ul krijgt via
       .et_pb_module_inner ul een forse bottom-marge/padding, en de
       beschrijving-module (et_pb_wc_description) duwt met zijn eigen
       marge alles ver uit elkaar --- */
.single-product .et_pb_module_inner ul.variable-items-wrapper,
.single-product div.product ul.variable-items-wrapper {
    margin: 0 !important;
    padding: 0 !important;
    list-style: none !important;
}
.single-product .et_pb_wc_description {
    margin-bottom: 12px !important;
}
.single-product form.variations_form table.variations td.value.woo-variation-items-wrapper,
.single-product form.variations_form table.variations td.value {
    margin: 0 !important;
    padding-bottom: 0 !important;
}

/* --- geen "Taal: Nederlands"-tekst achter de labels en geen wissen-link
       (reseller toont beide ook niet) --- */
.single-product .woo-selected-variation-item-name,
.single-product form.variations_form .reset_variations {
    display: none !important;
}
.single-product .woocommerce-variation-price .price {
    font-size: 24px;
    font-weight: 700;
}
CSS;
    wp_register_style('defibs-product-restyle', false, [], '1.5');
    wp_enqueue_style('defibs-product-restyle');
    wp_add_inline_style('defibs-product-restyle', $css);
}, 20);
