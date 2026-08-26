<?php
/**
 * Plugin Name: Points & Rewards Pro: performance-fix variabele producten
 * Description: Ontkoppelt het woocommerce_variable_price_html-filter van de
 *              Points & Rewards Pro-plugin. Dat filter bouwt via
 *              get_available_variations() ALLE variaties op (4x per
 *              productpagina, bij 200 variaties ~1,4s extra) alleen om
 *              "koop met punten"-producten een puntenprijs te tonen — een
 *              feature die deze shop niet gebruikt (0 producten met
 *              wps_product_purchase_points_only). Gemeten effect: warme
 *              productpagina van ~2,0s terug naar ~0,6s.
 *
 *              Ga je ooit "purchase through points only" gebruiken op
 *              variabele producten: verwijder dit bestand.
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
		if ( ! class_exists( 'Points_And_Rewards_For_Woocommerce_Pro_Public' ) ) {
			return; // Pro niet actief: niets te doen.
		}

		global $wp_filter;
		$hook = $wp_filter['woocommerce_variable_price_html'] ?? null;
		if ( ! $hook instanceof WP_Hook ) {
			return;
		}

		foreach ( $hook->callbacks as $prio => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'] ?? null;
				if (
					is_array( $fn )
					&& isset( $fn[0], $fn[1] )
					&& $fn[0] instanceof Points_And_Rewards_For_Woocommerce_Pro_Public
					&& 'wps_woocommerce_variable_price_html' === $fn[1]
				) {
					remove_filter( 'woocommerce_variable_price_html', $fn, $prio );
				}
			}
		}
	},
	999
);
