<?php
/**
 * Plugin Name: Shop manager: klanten bewerken + Login as User
 * Description: Maakt de rol afas_klant bewerkbaar voor shop managers, zodat zij
 *              via de "Login as User"-knop als klant kunnen inloggen (support).
 *              WooCommerce beperkt shop managers standaard tot de rol
 *              'customer', maar alle klanten hier hebben de rol 'afas_klant'.
 *              Vereist daarnaast eenmalig: wp cap add shop_manager edit_users
 *              Shop managers kunnen hiermee klantaccounts bewerken en overnemen,
 *              maar géén beheerders of andere shop managers.
 *
 * Author:      Defibrion
 * Version:     1.0
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_shop_manager_editable_roles',
	static function ( array $roles ): array {
		$roles[] = 'afas_klant';
		return array_unique( $roles );
	}
);
