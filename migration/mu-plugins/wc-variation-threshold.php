<?php
/**
 * Plugin Name: WooCommerce Variation Threshold
 * Description: Verhoogt WooCommerce's variatie-drempel naar 250. Variabele producten
 *              met t/m 250 variaties laden dan hun VOLLEDIGE variatie-matrix client-side
 *              i.p.v. de AJAX-modus (die standaard al >30 variaties intreedt). Daardoor
 *              kruist WooCommerce onmogelijke combinaties visueel uit (rood kruis /
 *              uitgegrijsd) op de productpagina, ook bij producten met veel variaties.
 *              Hoogste variatie-aantal nu ~201; 250 geeft ruimte voor groei.
 *
 *              Let op: producten met honderden variaties laden dan al hun variatie-data
 *              vooraf in de pagina (zwaardere HTML/JS). Voor een B2B-shop acceptabel.
 *
 * Author:      Defibrion
 * Version:     1.0
 *
 * Plaatsing: kopieer dit bestand naar <WP-root>/wp-content/mu-plugins/ (must-use,
 * auto-actief, geen activatie nodig).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
    'woocommerce_ajax_variation_threshold',
    static function ( $qty, $product ) {
        return 250;
    },
    10,
    2
);
