<?php
/**
 * Plugin Name: AFAS prijslijst-preview voor winkelmanagers
 * Description: Toont de AFAS prijslijst-dropdown op productpagina's (plugin
 *              lefcreative-afas-b2b) ook voor de rol Winkelmanager
 *              (shop_manager). De plugin gate't de dropdown hard op
 *              manage_options; deze mu-plugin wikkelt uitsluitend de drie
 *              preview-callbacks van de plugin in een wrapper die
 *              manage_options tijdelijk toekent aan gebruikers met
 *              manage_woocommerce. Buiten die drie codepaden verandert er
 *              niets aan capabilities. Raakt geen pluginbestanden aan en
 *              overleeft dus plugin-updates.
 *
 * Author:      Defibrion
 * Version:     1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * user_has_cap-filter: ken manage_options toe aan gebruikers die wél
 * manage_woocommerce hebben (winkelmanagers) maar geen echte manage_options.
 * Alleen actief zolang een gewrapte AFAS-callback draait.
 */
function defibrion_afas_preview_cap_grant( array $allcaps ): array {
	if ( empty( $allcaps['manage_options'] ) && ! empty( $allcaps['manage_woocommerce'] ) ) {
		$allcaps['manage_options'] = true;
	}
	return $allcaps;
}

add_action(
	'init',
	static function (): void {
		// AFAS-plugin niet actief (of class hernoemd na update): niets doen.
		// De dropdown blijft dan gewoon admin-only, verder geen effect.
		if ( ! class_exists( '\App\WooCommerce\PriceHooks' ) ) {
			return;
		}

		// De drie codepaden waarin de plugin op manage_options checkt:
		// - renderAdminPreviewBar: de dropdown-balk zelf
		// - enqueueScript: zet isAdmin/myPrijslijstId/prijslijstCounts in de
		//   JS-payload (zonder deze blijft de balk leeg/zonder tellingen)
		// - handleAdminPricePreviewAjax: prijs-lookup bij een selectie
		//   (zonder deze geeft elke selectie een 403)
		$rewire = array(
			array( 'woocommerce_single_product_summary', 'renderAdminPreviewBar', 4 ),
			array( 'wp_enqueue_scripts', 'enqueueScript', 10 ),
			array( 'wp_ajax_afas_admin_price_preview', 'handleAdminPricePreviewAjax', 10 ),
		);

		foreach ( $rewire as list( $hook, $method, $priority ) ) {
			// ::class levert de canonieke naam zonder leading backslash op —
			// exact de string waarmee de plugin (via self::class) registreerde.
			// Hook-IDs zijn een pure string-match, dus '\App\...' matcht NIET.
			$callback = array( \App\WooCommerce\PriceHooks::class, $method );

			// Alleen herregistreren als de originele hook er echt hing
			// (b2b_pricing_enabled kan uit staan, of een update kan de
			// registratie verplaatst hebben).
			if ( ! remove_action( $hook, $callback, $priority ) ) {
				continue;
			}

			add_action(
				$hook,
				static function ( ...$args ) use ( $callback ) {
					add_filter( 'user_has_cap', 'defibrion_afas_preview_cap_grant' );
					try {
						$callback( ...$args );
					} finally {
						remove_filter( 'user_has_cap', 'defibrion_afas_preview_cap_grant' );
					}
				},
				$priority
			);
		}
	},
	20
);
