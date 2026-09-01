<?php
/**
 * Plugin Name: ARKY webshop-aanpassingen (Kadence)
 * Description: Presentatie-tweaks voor de WooCommerce-webshop op het Kadence-
 *              thema — de twee-koloms-checkout van lefcreative-afas-b2b én het
 *              categorie-/shop-archief:
 *
 *              1. Coupon + points: het coupon-blok (incl. de points-velden, die
 *                 het points-and-rewards-plugin in checkout/form-coupon.php
 *                 meebakt) verhuist van de LINKERkolom naar ONDER het totaal in
 *                 de rechter order-summary-kolom. Notificatie-box-opmaak blijft.
 *
 *              2. AFAS-adresknoppen ("+ Nieuw adres" / "Bewerken"): die krijgen
 *                 in de plugin de leflite-klassen `btn bg-primary` mee, die
 *                 Kadence niet kent — ze ogen daardoor als kale tekstlinks. We
 *                 geven ze via CSS hetzelfde uiterlijk als de overige knoppen,
 *                 met Kadence's eigen knop-paletvariabelen (geen kleur-drift).
 *
 *              3. Edit-/nieuw-adresformulier: de knoppenrij (Opslaan/Annuleren)
 *                 staat op display:flex en kneep naast niet-geclearde floats in
 *                 een ~36px reststrook (tekst brak letter-voor-letter). clear:both
 *                 zet 'm onder de floats → volle breedte.
 *
 *              4. "Your reference"-veld: de label krijgt dezelfde sectiekop-stijl
 *                 als "Billing details", met het invulveld eronder.
 *
 *              5. Mobiel: in één kolom komt het besteloverzicht (rechterkolom)
 *                 bovenaan, daarna adres/referentie/facturatie (kolommen omgedraaid
 *                 met `order` binnen de max-width:768px-breakpoint).
 *
 *              6. Categorie-/shop-archief: smallere Kadence-sidebar (180px) +
 *                 kleinere gap (32px), zodat het merk/filter-lijstje niet zo'n
 *                 groot gat naar de productlijst laat; productkolom wordt breder.
 *                 Plus kleinere ruimte tussen de product-cards (40→20px) zodat
 *                 de cards zelf wat breder worden.
 *
 *              7. AFAS-factuuradres: de "Factuuradres"-titel staat als kop BÓVEN
 *                 een omkaderd blok (alleen een rand, geen achtergrond, rechte
 *                 hoeken) dat alléén het adres omsluit. De kaart-opmaak staat op
 *                 het <address> i.p.v. de wrapper, zodat de titel erboven valt; de
 *                 titel krijgt geen eigen opmaak zodat 'ie gelijk is aan de
 *                 "Afleveradres"-kop. Styling hier (niet in de plugin) zodat een
 *                 plugin-update 'm niet overschrijft.
 *
 *              Het AFAS-adresblok zelf (woocommerce_before_checkout_form prio
 *              100) blijft ongemoeid boven de facturatiegegevens links.
 *
 *              Layout-context (plugin/templates/checkout/form-checkout.php):
 *                links  = .afas-checkout-col-main   (coupon-login + form.checkout)
 *                rechts = .afas-checkout-col-review  (Your order + #order_review)
 *              De rechterkolom staat BUITEN form.checkout, dus het coupon-<form>
 *              nest niet in de checkout-form: geen geneste formulieren, de
 *              place-order-submit blijft intact.
 *
 * Author:      Defibrion
 * Version:     1.4
 *
 * Plaatsing: <WP-root>/wp-content/mu-plugins/ (must-use, auto-actief).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coupon-callback (de standaard WooCommerce-functie die checkout/form-coupon.php
 * laadt — door het points-plugin overschreven, dus inclusief de points-velden)
 * verplaatsen van de linkerkolom naar onder de totalen rechts.
 *
 * Op wp_loaded omdat WooCommerce de hook pas tijdens plugin-load registreert;
 * op mu-plugin-niveau zou remove_action te vroeg draaien en niets doen.
 */
add_action(
	'wp_loaded',
	static function () {
		if ( ! function_exists( 'woocommerce_checkout_coupon_form' ) ) {
			return;
		}
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		add_action( 'woocommerce_checkout_after_order_review', 'woocommerce_checkout_coupon_form', 10 );
	}
);

/**
 * AFAS-adresknoppen ("+ Nieuw adres" / "Bewerken") het uiterlijk van een gewone
 * knop geven. De plugin zet er `btn bg-primary btn-medium` op (leflite-klassen);
 * Kadence kent die niet, dus standaard zijn het kale onderstreepte tekstlinks.
 *
 * We mikken op de twee id's en gebruiken Kadence's eigen knop-paletvariabelen
 * (--global-palette-btn-*) zodat kleur + hover exact met de overige knoppen
 * meelopen. Fallback-kleuren = de waargenomen merk-primair (#004059 / wit).
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		$css = <<<CSS
.afas-checkout-address-selector .buttons-holder {
	display: flex;
	flex-wrap: wrap;
	gap: .5rem;
	margin-top: .75rem;
}
#afas-address-new-btn,
#afas-address-edit-btn {
	display: inline-block;
	background-color: var(--global-palette-btn-bg, #004059);
	color: var(--global-palette-btn-color, #fff) !important;
	border: 0;
	border-radius: 3px;
	padding: 6.4px 16px;
	font-size: 16px;
	line-height: 1.6;
	font-weight: 400;
	text-decoration: none !important;
	cursor: pointer;
	transition: background-color .2s ease, color .2s ease;
}
#afas-address-new-btn:hover,  #afas-address-edit-btn:hover,
#afas-address-new-btn:focus,  #afas-address-edit-btn:focus,
#afas-address-new-btn:active, #afas-address-edit-btn:active {
	background-color: var(--global-palette-btn-bg-hover, #00566f);
	color: var(--global-palette-btn-color-hover, #fff) !important;
	text-decoration: none !important;
}

/* Edit-/nieuw-adresformulier: de knoppenrij (Opslaan/Annuleren) staat in de
   plugin op `display:flex`. Daardoor krijgt die <p> een eigen block formatting
   context en kneep 'ie naast de NIET-geclearde floats van de voorgaande
   form-row-first/last-velden (Land/Postbus) in een ~36px reststrook — de
   knoppen werden letter-voor-letter afgebroken. clear:both zet de rij onder de
   floats zodat 'ie weer de volle breedte pakt. */
.afas-checkout-address-selector #afas-checkout-address-form > p.form-row:last-child {
	clear: both;
}

/* "Your reference"-veld: de label-tekst dezelfde sectiekop-stijl geven als
   "Billing details" (Kadence h3: var(--global-heading-font-family), 28px/700,
   #1a202c). Het invulveld blijft eronder (form-row-wide stapelt label > input). */
.afas-checkout-custom-fields label[for="afas-cf-rfcs"] {
	display: block;
	font-family: var(--global-heading-font-family, "Montserrat", sans-serif);
	font-size: 28px;
	font-weight: 700;
	line-height: 1.2;
	color: #1a202c;
	margin: 0 0 14px;
}
/* Het veld staat buiten form.checkout en mist daardoor WooCommerce's
   `.form-row .input-text { width:100% }`. Volle breedte zoals de billing-velden. */
.afas-checkout-custom-fields .form-row input,
.afas-checkout-custom-fields .form-row textarea,
.afas-checkout-custom-fields .form-row select {
	width: 100%;
}

/* AFAS-factuuradres: de "Factuuradres"-titel als kop BOVEN een kaart (alleen een
   rand, geen achtergrond, rechte hoeken) om alléén het adres. De plugin rendert
   h3.__title en address.__address als broers binnen .afas-invoice-summary; door de
   kaart-opmaak op het <address> te zetten i.p.v. op de wrapper, valt de titel er
   vanzelf boven. De titel krijgt bewust GEEN eigen opmaak, zodat 'ie als standaard-
   <h3> exact dezelfde formatting heeft als de "Afleveradres"-kop eronder. Styling
   hier (niet in de plugin) zodat een update 'm niet overschrijft. */
.afas-invoice-summary__address {
	display: block;
	padding: 1em 1.25em;
	border: 1px solid #e0e0e0;
	font-style: normal;
	line-height: 1.6;
	color: #333;
}
.afas-invoice-summary__name {
	display: block;
	font-weight: 600;
}

/* Mobiel (één kolom): besteloverzicht (rechterkolom) bovenaan, daarna
   adres/referentie/facturatie. .afas-checkout-cols is een grid; de plugin valt
   bij max-width:768px terug op één kolom — daar draaien we met `order` de twee
   kolom-items om (review vóór main). Zelfde breakpoint als de plugin. */
@media (max-width: 768px) {
	.afas-checkout-cols .afas-checkout-col-review { order: -1; }
	.afas-checkout-cols .afas-checkout-col-main   { order: 0; }
}
CSS;

		wp_register_style( 'arky-checkout-tweaks', false );
		wp_enqueue_style( 'arky-checkout-tweaks' );
		wp_add_inline_style( 'arky-checkout-tweaks', $css );
	}
);

/**
 * Categorie-/shop-archief: de Kadence-sidebar-kolom is standaard ~313px breed
 * (≈27% van de content), terwijl het merk-/filterlijstje maar ~116px vult — plus
 * een 56px grid-gap. Dat leest als een groot gat tussen filter en productlijst.
 * We zetten op de product-archieven een smallere sidebar (180px) + kleinere gap
 * (32px); de productkolom wordt navenant breder. Alleen op shop/categorie/tag,
 * zodat sidebars elders (blog e.d.) ongemoeid blijven.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return;
		}

		$css = <<<CSS
body.has-left-sidebar.tax-product_cat .content-container,
body.has-left-sidebar.tax-product_tag .content-container,
body.has-left-sidebar.post-type-archive-product .content-container {
	grid-template-columns: 180px minmax(0, 1fr) !important;
	column-gap: 32px !important;
}
/* Product-grid: kleinere ruimte tussen de cards (Kadence-standaard 40px) →
   de kolommen verdelen de vrijgekomen ruimte en worden breder. */
body.tax-product_cat ul.products.grid-cols,
body.tax-product_tag ul.products.grid-cols,
body.post-type-archive-product ul.products.grid-cols {
	gap: 20px !important;
}
CSS;

		wp_register_style( 'arky-shop-archive-tweaks', false );
		wp_enqueue_style( 'arky-shop-archive-tweaks' );
		wp_add_inline_style( 'arky-shop-archive-tweaks', $css );
	}
);
