<?php
/**
 * Plugin Name: CCD Healthcheck
 * Description: Fallback /ccdhealth (HTTP 200) se o Alias Apache nao atender. Canonico: /ccdhealth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preferir Alias Apache /ccdhealth → wp-content/ccd-health-ok.txt (sem bootstrap WP).
 * Este hook so corre se o pedido chegar ao PHP.
 */
add_action(
	'muplugins_loaded',
	static function () {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = (string) parse_url( $uri, PHP_URL_PATH );
		$path = rtrim( $path, '/' );
		if ( $path !== '/ccdhealth' ) {
			return;
		}

		if ( function_exists( 'status_header' ) ) {
			status_header( 200 );
		} else {
			header( 'HTTP/1.1 200 OK' );
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo 'ok';
		exit;
	},
	0
);
