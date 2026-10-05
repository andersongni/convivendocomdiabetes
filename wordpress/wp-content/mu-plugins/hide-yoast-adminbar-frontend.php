<?php
/**
 * Plugin Name: CCD Hide Frontend Admin Bar
 * Description: Oculta a barra superior do WordPress no front, mesmo com usuario logado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No front: nunca mostrar a admin bar (visitante ou logado).
 * No wp-admin a barra continua disponivel.
 */
add_filter(
	'show_admin_bar',
	static function ( $show ) {
		if ( is_admin() ) {
			return $show;
		}
		return false;
	},
	99
);
