<?php
/**
 * Plugin Name: Variations JSON cache (Redis)
 * Description: Cachet het resultaat van WC_Product_Variable::get_available_variations()
 *              in de object cache (Redis). Op producten met veel variaties (de
 *              variatie-drempel staat hier op 250 zodat onmogelijke combinaties
 *              client-side uitgegrijsd worden) kost het server-side opbouwen van
 *              die payload ~0,8s per paginaweergave: 200× get_available_variation
 *              met price_html (incl. AFAS-prijsresolutie en Points&Rewards-filters)
 *              en attachment-props per variatie.
 *
 *              De cache-key bevat: product-id + laatst-gewijzigd, de ingelogde
 *              gebruiker (prijzen zijn klantspecifiek: AFAS relatie-prijzen,
 *              prijslijsten, kortingsgroepen én punten-niveaukorting), en de
 *              AFAS-prijzen-cacheversie (groep lef_afas_prices) zodat een
 *              prijzen-hersync die PriceResolver::flushCache() aanroept ook
 *              deze payload automatisch laat verlopen.
 *
 *              Alleen actief bij een persistente object cache (Redis); zonder
 *              die cache is opslaan per request zinloos en doet dit bestand niets.
 *
 * Author:      Defibrion
 * Version:     1.0
 *
 * Plaatsing: <WP-root>/wp-content/mu-plugins/ (must-use, auto-actief).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_loaded',
	static function () {
		if ( ! function_exists( 'woocommerce_variable_add_to_cart' ) ) {
			return;
		}
		// Zonder persistente object cache heeft per-request cachen geen zin.
		if ( ! wp_using_ext_object_cache() ) {
			return;
		}

		remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
		add_action( 'woocommerce_variable_add_to_cart', 'arky_cached_variable_add_to_cart', 30 );
	}
);

/**
 * Identiek aan WooCommerce's woocommerce_variable_add_to_cart(), maar met de
 * dure get_available_variations()-aanroep achter een object-cache-key.
 */
function arky_cached_variable_add_to_cart() {
	global $product;

	wp_enqueue_script( 'wc-add-to-cart-variation' );

	$get_variations = count( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

	$available_variations = false;
	if ( $get_variations ) {
		$group = 'arky_variations_json';

		$key = sprintf(
			'var_json:%d:%s:u%d:p%s',
			$product->get_id(),
			$product->get_date_modified() ? $product->get_date_modified()->getTimestamp() : '0',
			get_current_user_id(), // prijzen/kortingen zijn klantspecifiek
			arky_afas_prices_version()
		);

		$available_variations = wp_cache_get( $key, $group );
		if ( ! is_array( $available_variations ) ) {
			$available_variations = $product->get_available_variations();
			wp_cache_set( $key, $available_variations, $group, 6 * HOUR_IN_SECONDS );
		}
	}

	wc_get_template(
		'single-product/add-to-cart/variable.php',
		array(
			'available_variations' => $available_variations,
			'attributes'           => $product->get_variation_attributes(),
			'selected_attributes'  => $product->get_default_attributes(),
		)
	);
}

/**
 * Versie-signaal voor de AFAS-prijsdata, gebruikt in de cache-key zodat een
 * prijzensync de gecachete variaties-payloads automatisch laat verlopen.
 *
 * 1. Voorkeur: de versie-key van de PriceResolver-cachegroep (aanwezig zodra
 *    de PriceResolver-performancepatch live staat, die 'm bumpt via flushCache
 *    na elke sync).
 * 2. Fallback (werkt op de huidige, ongepatchte plugin): MAX(synced_at) uit de
 *    lef_afas_prijzen-tabel — SyncPrijzenJob stempelt elke rij met de synctijd,
 *    dus die waarde verandert bij elke prijzensync. Micro-gecachet voor 60s
 *    zodat het geen query per paginaweergave kost; verouderde prijzen zijn zo
 *    maximaal ~1 minuut zichtbaar na een sync.
 */
function arky_afas_prices_version(): string {
	$ver = wp_cache_get( 'ver', 'lef_afas_prices' );
	if ( $ver !== false ) {
		return (string) $ver;
	}

	$ver = wp_cache_get( 'synced_at_ver', 'arky_variations_json' );
	if ( $ver === false ) {
		global $wpdb;
		$table = $wpdb->prefix . 'lef_afas_prijzen';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ver = (string) $wpdb->get_var( "SELECT MAX(synced_at) FROM `{$table}`" );
		wp_cache_set( 'synced_at_ver', $ver, 'arky_variations_json', MINUTE_IN_SECONDS );
	}

	return $ver;
}
