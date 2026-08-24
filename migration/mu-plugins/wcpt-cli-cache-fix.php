<?php
/**
 * Fix voor wc-product-table-pro onder WP-CLI.
 *
 * Onder WP-CLI laadt WordPress plugins binnen een methode (function scope),
 * waardoor de top-level `$wcpt_variations_cache = array();` in main.php een
 * lokale variabele wordt in plaats van de global. Zodra een product via CLI
 * wordt opgeslagen (o.a. de AFAS-artikelen-sync) crasht
 * wcpt_invalidate_product_variations_cache() dan fataal op array_keys(null).
 *
 * Deze shim initialiseert de global vooraf; op web-requests zet de plugin
 * hem zelf opnieuw op [] — geen gedragsverandering.
 */
if (!isset($GLOBALS['wcpt_variations_cache']) || !is_array($GLOBALS['wcpt_variations_cache'])) {
    $GLOBALS['wcpt_variations_cache'] = [];
}
