<?php
/**
 * Plugin Name: Stukprijzen in orderoverzichten
 * Description: Toont onder elk regeltotaal ook de prijs per stuk (bij aantal
 *              > 1), in orderbevestigingsmails én op de bedankpagina/Mijn
 *              account-orderweergave. Rekent vanuit de werkelijke orderregel
 *              (dus inclusief AFAS-klantprijzen en kortingen), volgt de
 *              btw-weergave-instelling van de shop, en kiest het label op
 *              basis van de actieve taal op het moment van renderen (dus ook
 *              correct wanneer een meertaligheidsplugin de e-mailtaal per
 *              klant wisselt).
 *
 * Author:      Defibrion
 * Version:     1.2
 *
 * Plaatsing: <WP-root>/wp-content/mu-plugins/ (must-use, auto-actief).
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_order_formatted_line_subtotal',
	static function ( $subtotal, $item, $order ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return $subtotal;
		}

		$qty = (int) $item->get_quantity();
		if ( $qty <= 1 ) {
			return $subtotal; // bij 1 stuk is het regeltotaal al de stukprijs
		}

		// Zelfde grondslag als het getoonde regeltotaal: subtotal (vóór
		// couponkorting), incl./excl. btw volgens de shopinstelling.
		if ( 'excl' === get_option( 'woocommerce_tax_display_cart' ) ) {
			$unit = (float) $item->get_subtotal() / $qty;
		} else {
			$unit = ( (float) $item->get_subtotal() + (float) $item->get_subtotal_tax() ) / $qty;
		}

		$unit_html = wc_price( $unit, array( 'currency' => $order->get_currency() ) );

		// Label per taal, bepaald op rendermoment (site-locale, of de door een
		// meertaligheidsplugin gewisselde e-mailtaal).
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$labels = array(
			'nl' => '%s per stuk',
			'de' => '%s pro Stück',
			'fr' => '%s par pièce',
			'es' => '%s por unidad',
		);
		$lang  = substr( $locale, 0, 2 );
		$label = isset( $labels[ $lang ] ) ? $labels[ $lang ] : '%s per unit';

		return $subtotal . '<br><small class="unit-price">' . sprintf( $label, $unit_html ) . '</small>';
	},
	10,
	3
);
